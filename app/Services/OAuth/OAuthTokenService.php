<?php

namespace App\Services\OAuth;

use App\Models\AuthorizationCode;
use App\Models\Token;
use App\Models\TokenPolicy;
use App\Models\SsoClient;
use App\Models\User;
use App\Repositories\Contracts\TokenRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type OAuthTokenPayload array{
 *     client_id: string,
 *     client_secret?: string|null,
 *     code?: string,
 *     redirect_uri?: string,
 *     code_verifier?: string|null,
 *     refresh_token?: string,
 *     token?: string,
 *     token_type_hint?: string|null,
 *     reason?: string|null
 * }
 * @phpstan-type TokenPair array{
 *     token_type: string,
 *     access_token: string,
 *     refresh_token: string,
 *     expires_in: int,
 *     refresh_token_expires_in: int,
 *     scope: string
 * }
 * @phpstan-type IssuedTokenPair array{
 *     token: Token,
 *     payload: TokenPair
 * }
 * @phpstan-type IntrospectionResponse array{
 *     active: bool,
 *     token_type?: string,
 *     client_id?: string,
 *     scope?: string,
 *     sub?: string,
 *     exp?: int|null
 * }
 * @phpstan-type UserInfoClaims array{
 *     sub: string,
 *     name?: string,
 *     email?: string,
 *     email_verified?: bool
 * }
 */
class OAuthTokenService
{
    public function __construct(
        private readonly RedirectUriMatcher $redirectUriMatcher,
        private readonly PkceVerifier $pkceVerifier,
        private readonly TokenRepositoryInterface $tokenRepository,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * Exchange a validated authorization code for an access and refresh token pair.
     *
     * @param OAuthTokenPayload $payload
     * @return TokenPair
     */
    public function exchangeAuthorizationCode(array $payload, ?string $ipAddress, ?string $userAgent): array
    {
        $client = $this->resolveClient((string) $payload['client_id']);
        $this->assertClientAuthentication($client, (string) ($payload['client_secret'] ?? ''));

        $plainCode = (string) $payload['code'];
        $authorizationCode = AuthorizationCode::query()
            ->with(['client', 'tokenPolicy', 'user'])
            ->where('code_hash', hash('sha256', $plainCode))
            ->first();

        if ($authorizationCode === null) {
            $this->logGrantFailure($client, 'invalid_authorization_code');
            throw ValidationException::withMessages(['code' => 'The provided authorization code is invalid.']);
        }

        if ($authorizationCode->sso_client_id !== $client->id) {
            $this->logGrantFailure($client, 'authorization_code_client_mismatch');
            throw ValidationException::withMessages(['code' => 'The authorization code does not belong to this client.']);
        }

        if ($authorizationCode->consumed_at !== null || $authorizationCode->revoked_at !== null || $authorizationCode->expires_at->isPast()) {
            $this->logGrantFailure($client, 'authorization_code_inactive');
            throw ValidationException::withMessages(['code' => 'The authorization code is expired, revoked, or already used.']);
        }

        $redirectUri = trim((string) $payload['redirect_uri']);
        if (! $this->redirectUriMatcher->matches($client, $redirectUri) || ! hash_equals($authorizationCode->redirect_uri, $redirectUri)) {
            $this->logGrantFailure($client, 'redirect_uri_mismatch');
            throw ValidationException::withMessages(['redirect_uri' => 'The redirect URI does not match the authorization request.']);
        }

        $verifier = trim((string) ($payload['code_verifier'] ?? ''));
        if ($verifier === '') {
            $this->logGrantFailure($client, 'missing_code_verifier');
            throw ValidationException::withMessages(['code_verifier' => 'The code verifier field is required.']);
        }

        $this->pkceVerifier->verify($authorizationCode->code_challenge, $authorizationCode->code_challenge_method, $verifier);
        $policy = $this->resolvePolicy($client, $authorizationCode->tokenPolicy);

        return DB::transaction(function () use ($authorizationCode, $client, $policy, $ipAddress, $userAgent): array {
            $authorizationCode->forceFill(['consumed_at' => now()])->save();

            $issued = $this->issueTokenPair(
                client: $client,
                userId: $authorizationCode->user_id,
                policy: $policy,
                scopes: $authorizationCode->scopes ?? [],
                authorizationCodeId: $authorizationCode->id,
                parentTokenId: null,
                familyId: (string) Str::uuid(),
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_OAUTH,
                event: 'oauth.token.issued',
                description: 'OAuth token issued.',
                subject: $client,
                causer: $authorizationCode->user,
                properties: [
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'token_id' => $issued['token']->id,
                    'user_id' => $authorizationCode->user_id,
                    'family_id' => $issued['token']->family_id,
                    'grant_type' => 'authorization_code',
                    'scope_codes' => $authorizationCode->scopes ?? [],
                ],
            );

            $this->logIssuedTokens(
                token: $issued['token'],
                client: $client,
                grantType: 'authorization_code',
                scopeCodes: $authorizationCode->scopes ?? [],
                causer: $authorizationCode->user,
            );

            return $issued['payload'];
        });
    }

    /**
     * Refresh an access token using a validated refresh token grant payload.
     *
     * @param OAuthTokenPayload $payload
     * @return TokenPair
     */
    public function refreshAccessToken(array $payload, ?string $ipAddress, ?string $userAgent): array
    {
        $client = $this->resolveClient((string) $payload['client_id']);
        $this->assertClientAuthentication($client, (string) ($payload['client_secret'] ?? ''));

        $plainRefreshToken = (string) $payload['refresh_token'];
        $token = $this->tokenRepository->findTokenWithRelationsByRefreshHash(hash('sha256', $plainRefreshToken));

        if ($token === null || $token->sso_client_id !== $client->id) {
            $this->logGrantFailure($client, 'invalid_refresh_token');
            throw ValidationException::withMessages(['refresh_token' => 'The provided refresh token is invalid.']);
        }

        if ($token->refresh_token_expires_at === null || $token->refresh_token_expires_at->isPast()) {
            $this->logGrantFailure($client, 'refresh_token_inactive');
            throw ValidationException::withMessages(['refresh_token' => 'The refresh token is expired or revoked.']);
        }

        $policy = $this->resolvePolicy($client, $token->tokenPolicy);

        if (! $token->isRefreshTokenActive()) {
            $this->handleRefreshTokenReuse($token, $client, $policy);
        }

        return DB::transaction(function () use ($token, $client, $policy, $ipAddress, $userAgent): array {
            $issued = $this->issueTokenPair(
                client: $client,
                userId: $token->user_id,
                policy: $policy,
                scopes: $token->scopes ?? [],
                authorizationCodeId: null,
                parentTokenId: $token->id,
                familyId: $token->family_id ?: (string) Str::uuid(),
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            if ($policy->refresh_token_rotation_enabled || $policy->reuse_refresh_token_forbidden) {
                $this->tokenRepository->markRefreshTokenRotated($token, $issued['token']);

                $this->auditLogService->logSuccess(
                    logName: AuditLogService::LOG_OAUTH,
                    event: 'oauth.refresh_token.rotated',
                    description: 'OAuth refresh token rotated.',
                    subject: $client,
                    causer: $token->user,
                    properties: [
                        'client_id' => $client->id,
                        'client_public_id' => $client->client_id,
                        'token_id' => $token->id,
                        'user_id' => $token->user_id,
                        'family_id' => $token->family_id,
                        'parent_token_id' => $token->parent_token_id,
                        'replaced_by_token_id' => $issued['token']->id,
                        'decision' => 'rotated',
                    ],
                );
            }

            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_OAUTH,
                event: 'oauth.token.refreshed',
                description: 'OAuth token refreshed.',
                subject: $client,
                causer: $token->user,
                properties: [
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'grant_type' => 'refresh_token',
                    'scope_codes' => $token->scopes ?? [],
                    'token_id' => $issued['token']->id,
                    'family_id' => $issued['token']->family_id,
                    'parent_token_id' => $issued['token']->parent_token_id,
                ],
            );

            $this->logIssuedTokens(
                token: $issued['token'],
                client: $client,
                grantType: 'refresh_token',
                scopeCodes: $token->scopes ?? [],
                causer: $token->user,
            );

            return $issued['payload'];
        });
    }

    /**
     * Resolve an active confidential or public client by OAuth client identifier.
     */
    private function resolveClient(string $clientId): SsoClient
    {
        /** @var SsoClient|null $client */
        $client = SsoClient::query()->with(['activeSecrets', 'tokenPolicy'])->where('client_id', $clientId)->where('is_active', true)->first();

        if ($client === null) {
            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_OAUTH,
                event: 'oauth.client_auth.failed',
                description: 'OAuth client authentication failed.',
                properties: [
                    'client_public_id' => $clientId,
                    'reason' => 'invalid_client',
                ],
            );

            throw ValidationException::withMessages(['client_id' => 'The provided client is invalid or inactive.']);
        }

        return $client;
    }

    /**
     * Validate the provided client secret against the currently active secret set.
     */
    private function assertClientAuthentication(SsoClient $client, string $clientSecret): void
    {
        $activeSecrets = $client->activeSecrets()->get();

        if ($activeSecrets->isEmpty()) {
            return;
        }

        foreach ($activeSecrets as $secret) {
            if (Hash::check($clientSecret, $secret->secret_hash)) {
                return;
            }
        }

        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.client_auth.failed',
            description: 'OAuth client authentication failed.',
            subject: $client,
            properties: [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'reason' => 'invalid_client_secret',
            ],
        );

        throw ValidationException::withMessages(['client_secret' => 'The provided client secret is invalid.']);
    }

    /**
     * Resolve the token policy explicitly attached to the request client or fall back to the active default.
     */
    private function resolvePolicy(SsoClient $client, ?TokenPolicy $policy = null): TokenPolicy
    {
        if ($policy instanceof TokenPolicy && $policy->is_active) {
            return $policy;
        }

        $resolved = TokenPolicy::query()
            ->when($client->token_policy_id !== null, fn ($query) => $query->whereKey($client->token_policy_id))
            ->when($client->token_policy_id === null, fn ($query) => $query->where('is_default', true))
            ->where('is_active', true)
            ->first();

        if ($resolved === null) {
            throw ValidationException::withMessages(['client_id' => 'No active token policy is available for this client.']);
        }

        return $resolved;
    }

    /**
     * Issue and persist a fresh token pair for the given client and user context.
     *
     * @param array<int, string> $scopes
     * @return IssuedTokenPair
     */
    private function issueTokenPair(
        SsoClient $client,
        int $userId,
        TokenPolicy $policy,
        array $scopes,
        ?int $authorizationCodeId,
        ?int $parentTokenId,
        string $familyId,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        $plainAccessToken = Str::random(80);
        $plainRefreshToken = Str::random(80);

        $accessExpiresAt = now()->addMinutes($policy->access_token_ttl_minutes);
        $refreshExpiresAt = now()->addMinutes($policy->refresh_token_ttl_minutes);

        $token = $this->tokenRepository->createTokenPair([
            'sso_client_id' => $client->id,
            'user_id' => $userId,
            'token_policy_id' => $policy->id,
            'authorization_code_id' => $authorizationCodeId,
            'parent_token_id' => $parentTokenId,
            'family_id' => $familyId,
            'access_token_hash' => hash('sha256', $plainAccessToken),
            'refresh_token_hash' => hash('sha256', $plainRefreshToken),
            'scopes' => array_values($scopes),
            'access_token_expires_at' => $accessExpiresAt,
            'refresh_token_expires_at' => $refreshExpiresAt,
            'issued_from_ip' => $ipAddress,
            'user_agent' => $userAgent,
            'meta' => [
                'issued_via' => $authorizationCodeId === null ? 'refresh_token' : 'authorization_code',
            ],
        ]);

        return [
            'token' => $token,
            'payload' => [
                'token_type' => 'Bearer',
                'access_token' => $plainAccessToken,
                'refresh_token' => $plainRefreshToken,
                'expires_in' => (int) ceil(now()->diffInSeconds($accessExpiresAt)),
                'refresh_token_expires_in' => (int) ceil(now()->diffInSeconds($refreshExpiresAt)),
                'scope' => implode(' ', $scopes),
            ],
        ];
    }

    /**
     * Revoke an OAuth access or refresh token owned by the requesting client.
     *
     * @param OAuthTokenPayload $payload
     */
    public function revokeToken(array $payload): void
    {
        $client = $this->resolveClient((string) $payload['client_id']);
        $this->assertClientAuthentication($client, (string) ($payload['client_secret'] ?? ''));

        $plainToken = (string) $payload['token'];
        $tokenHash = hash('sha256', $plainToken);
        $tokenTypeHint = $this->normalizeTokenTypeHint($payload['token_type_hint'] ?? null);
        $reason = is_string($payload['reason'] ?? null) && trim((string) $payload['reason']) !== ''
            ? trim((string) $payload['reason'])
            : null;

        DB::transaction(function () use ($client, $tokenHash, $tokenTypeHint, $reason): void {
            if ($tokenTypeHint === 'access_token') {
                $token = $this->tokenRepository->findActiveAccessTokenByHash($tokenHash);

                if ($token === null || (int) $token->sso_client_id !== (int) $client->id) {
                    return;
                }

                $this->tokenRepository->revokeAccessToken($token, is_string($reason) ? $reason : null);

                $this->auditLogService->logSuccess(
                    logName: AuditLogService::LOG_OAUTH,
                    event: 'oauth.token.revoked',
                    description: 'OAuth token revoked.',
                    subject: $client,
                    causer: $token->user,
                    properties: [
                        'client_id' => $client->id,
                        'client_public_id' => $client->client_id,
                        'token_id' => $token->id,
                        'user_id' => $token->user_id,
                        'family_id' => $token->family_id,
                        'token_kind' => 'access_token',
                        'revoked_reason' => $reason,
                    ],
                );

                return;
            }

            if ($tokenTypeHint === 'refresh_token') {
                $token = $this->tokenRepository->findActiveRefreshTokenByHash($tokenHash);

                if ($token === null || (int) $token->sso_client_id !== (int) $client->id) {
                    return;
                }

                $this->tokenRepository->revokeRefreshToken($token, is_string($reason) ? $reason : null);

                $this->auditLogService->logSuccess(
                    logName: AuditLogService::LOG_OAUTH,
                    event: 'oauth.token.revoked',
                    description: 'OAuth token revoked.',
                    subject: $client,
                    causer: $token->user,
                    properties: [
                        'client_id' => $client->id,
                        'client_public_id' => $client->client_id,
                        'token_id' => $token->id,
                        'user_id' => $token->user_id,
                        'family_id' => $token->family_id,
                        'token_kind' => 'refresh_token',
                        'revoked_reason' => $reason,
                    ],
                );

                return;
            }

            $accessToken = $this->tokenRepository->findActiveAccessTokenByHash($tokenHash);

            if ($accessToken !== null && (int) $accessToken->sso_client_id === (int) $client->id) {
                $this->tokenRepository->revokeAccessToken($accessToken, is_string($reason) ? $reason : null);

                $this->auditLogService->logSuccess(
                    logName: AuditLogService::LOG_OAUTH,
                    event: 'oauth.token.revoked',
                    description: 'OAuth token revoked.',
                    subject: $client,
                    causer: $accessToken->user,
                    properties: [
                        'client_id' => $client->id,
                        'client_public_id' => $client->client_id,
                        'token_id' => $accessToken->id,
                        'user_id' => $accessToken->user_id,
                        'family_id' => $accessToken->family_id,
                        'token_kind' => 'access_token',
                        'revoked_reason' => $reason,
                    ],
                );

                return;
            }

            $refreshToken = $this->tokenRepository->findActiveRefreshTokenByHash($tokenHash);

            if ($refreshToken !== null && (int) $refreshToken->sso_client_id === (int) $client->id) {
                $this->tokenRepository->revokeRefreshToken($refreshToken, is_string($reason) ? $reason : null);

                $this->auditLogService->logSuccess(
                    logName: AuditLogService::LOG_OAUTH,
                    event: 'oauth.token.revoked',
                    description: 'OAuth token revoked.',
                    subject: $client,
                    causer: $refreshToken->user,
                    properties: [
                        'client_id' => $client->id,
                        'client_public_id' => $client->client_id,
                        'token_id' => $refreshToken->id,
                        'user_id' => $refreshToken->user_id,
                        'family_id' => $refreshToken->family_id,
                        'token_kind' => 'refresh_token',
                        'revoked_reason' => $reason,
                    ],
                );
            }
        });
    }

    /**
     * Introspect an OAuth access or refresh token for the requesting client.
     *
     * @param OAuthTokenPayload $payload
     * @return IntrospectionResponse
     */
    public function introspectToken(array $payload): array
    {
        $client = $this->resolveClient((string) $payload['client_id']);
        $this->assertClientAuthentication($client, (string) ($payload['client_secret'] ?? ''));

        $tokenHash = hash('sha256', (string) $payload['token']);
        $tokenTypeHint = $this->normalizeTokenTypeHint($payload['token_type_hint'] ?? null);
        $response = $this->resolveIntrospectionResponse($client, $tokenHash, $tokenTypeHint);

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.token.introspected',
            description: 'OAuth token introspection completed.',
            subject: $client,
            properties: [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'token_kind' => $response['token_type'] ?? $tokenTypeHint,
                'status' => $response['active'] ? 'active' : 'inactive',
            ],
        );

        return $response;
    }

    /**
     * Resolve OIDC-compatible user info claims for a valid Bearer access token.
     *
     * @return UserInfoClaims
     */
    public function getUserInfo(?string $plainAccessToken, ?string $ipAddress, ?string $userAgent): array
    {
        $token = $this->resolveUserInfoAccessToken($plainAccessToken);

        if (! in_array('openid', $token->scopes ?? [], true)) {
            throw new AuthorizationException('The access token does not grant the openid scope.');
        }

        $claims = $this->formatUserInfoClaims($token->user, $token->scopes ?? []);

        $token->forceFill([
            'last_used_at' => now(),
            'issued_from_ip' => $token->issued_from_ip ?? $ipAddress,
            'user_agent' => $token->user_agent ?? $userAgent,
        ])->save();

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.userinfo.requested',
            description: 'OAuth user info retrieved.',
            subject: $token->client,
            causer: $token->user,
            properties: [
                'client_id' => $token->client->id,
                'client_public_id' => $token->client->client_id,
                'scope_codes' => $token->scopes ?? [],
            ],
        );

        return $claims;
    }

    /**
     * Normalize the optional token hint to the supported OAuth token type values.
     */
    private function normalizeTokenTypeHint(mixed $hint): ?string
    {
        if (! \is_string($hint)) {
            return null;
        }

        $normalized = trim($hint);

        return match ($normalized) {
            'access_token' => 'access_token',
            'refresh_token' => 'refresh_token',
            default => null,
        };
    }

    /**
     * @return IntrospectionResponse
     */
    private function resolveIntrospectionResponse(SsoClient $client, string $tokenHash, ?string $tokenTypeHint): array
    {
        if ($tokenTypeHint === 'access_token') {
            return $this->introspectAccessToken($client, $tokenHash);
        }

        if ($tokenTypeHint === 'refresh_token') {
            return $this->introspectRefreshToken($client, $tokenHash);
        }

        $accessTokenResponse = $this->introspectAccessToken($client, $tokenHash);

        if ($accessTokenResponse['active'] === true) {
            return $accessTokenResponse;
        }

        return $this->introspectRefreshToken($client, $tokenHash);
    }

    /**
     * @return IntrospectionResponse
     */
    private function introspectAccessToken(SsoClient $client, string $tokenHash): array
    {
        $token = $this->tokenRepository->findAccessTokenByHash($tokenHash);

        if (! $token instanceof Token || (int) $token->sso_client_id !== (int) $client->id) {
            return $this->inactiveIntrospectionResponse();
        }

        if (! $this->isAccessTokenActive($token)) {
            return $this->inactiveIntrospectionResponse();
        }

        return $this->formatIntrospectionResponse($token, 'access_token');
    }

    /**
     * @return IntrospectionResponse
     */
    private function introspectRefreshToken(SsoClient $client, string $tokenHash): array
    {
        $token = $this->tokenRepository->findRefreshTokenByHash($tokenHash);

        if (! $token instanceof Token || (int) $token->sso_client_id !== (int) $client->id) {
            return $this->inactiveIntrospectionResponse();
        }

        if (! $this->isRefreshTokenActive($token)) {
            return $this->inactiveIntrospectionResponse();
        }

        return $this->formatIntrospectionResponse($token, 'refresh_token');
    }

    /**
     * Determine whether the stored access token is still active and usable.
     */
    private function isAccessTokenActive(Token $token): bool
    {
        return $token->isAccessTokenActive();
    }

    /**
     * Determine whether the stored refresh token is still active and usable.
     */
    private function isRefreshTokenActive(Token $token): bool
    {
        return $token->isRefreshTokenActive();
    }

    /**
     * Format a standards-aligned token introspection payload.
     *
     * @return IntrospectionResponse
     */
    private function formatIntrospectionResponse(Token $token, string $tokenType): array
    {
        $expiresAt = $tokenType === 'access_token'
            ? $token->access_token_expires_at
            : $token->refresh_token_expires_at;

        return [
            'active' => true,
            'token_type' => $tokenType,
            'client_id' => (string) $token->client->client_id,
            'scope' => implode(' ', $token->scopes ?? []),
            'sub' => (string) $token->user_id,
            'exp' => $expiresAt?->getTimestamp(),
        ];
    }

    /**
     * @return IntrospectionResponse
     */
    private function inactiveIntrospectionResponse(): array
    {
        return ['active' => false];
    }

    /**
     * Resolve a Bearer access token to the persisted token model with client and user context.
     */
    private function resolveUserInfoAccessToken(?string $plainAccessToken): Token
    {
        $normalizedToken = trim((string) $plainAccessToken);

        if ($normalizedToken === '') {
            throw new AuthenticationException('A valid Bearer access token is required.');
        }

        $token = $this->tokenRepository->findAccessTokenWithUserAndClientByHash(hash('sha256', $normalizedToken));

        if (! $token instanceof Token || ! $this->isAccessTokenActive($token)) {
            throw new AuthenticationException('The provided access token is invalid, expired, or revoked.');
        }

        return $token;
    }

    /**
     * Build the user info response body from the granted OpenID scopes.
     *
     * @param array<int, string> $scopes
     * @return UserInfoClaims
     */
    private function formatUserInfoClaims(User $user, array $scopes): array
    {
        $claims = [
            'sub' => (string) $user->id,
        ];

        if (in_array('profile', $scopes, true)) {
            $claims['name'] = $user->name;
        }

        if (in_array('email', $scopes, true)) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = $user->email_verified_at !== null;
        }

        return $claims;
    }

    /**
     * Record a token grant failure event for auditing and abuse investigation.
     */
    private function logGrantFailure(SsoClient $client, string $reason): void
    {
        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.token.grant_failed',
            description: 'OAuth token grant failed.',
            subject: $client,
            properties: [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'reason' => $reason,
            ],
        );
    }

    /**
     * @param array<int, string> $scopeCodes
     */
    private function logIssuedTokens(Token $token, SsoClient $client, string $grantType, array $scopeCodes, ?User $causer): void
    {
        $sharedProperties = [
            'client_id' => $client->id,
            'client_public_id' => $client->client_id,
            'user_id' => $token->user_id,
            'token_id' => $token->id,
            'family_id' => $token->family_id,
            'parent_token_id' => $token->parent_token_id,
            'grant_type' => $grantType,
            'scope_codes' => $scopeCodes,
        ];

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.access_token.issued',
            description: 'OAuth access token issued.',
            subject: $client,
            causer: $causer,
            properties: [
                ...$sharedProperties,
                'token_kind' => 'access_token',
            ],
        );

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.refresh_token.issued',
            description: 'OAuth refresh token issued.',
            subject: $client,
            causer: $causer,
            properties: [
                ...$sharedProperties,
                'token_kind' => 'refresh_token',
            ],
        );
    }

    private function handleRefreshTokenReuse(Token $token, SsoClient $client, TokenPolicy $policy): never
    {
        DB::transaction(function () use ($token, $client, $policy): void {
            $reusedToken = $this->tokenRepository->markRefreshReuseDetected($token);

            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_OAUTH,
                event: 'oauth.refresh_token.reuse_detected',
                description: 'OAuth refresh token reuse detected.',
                subject: $client,
                causer: $token->user,
                properties: [
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'token_id' => $reusedToken->id,
                    'user_id' => $reusedToken->user_id,
                    'family_id' => $reusedToken->family_id,
                    'parent_token_id' => $reusedToken->parent_token_id,
                    'replaced_by_token_id' => $reusedToken->replaced_by_token_id,
                    'decision' => 'denied',
                    'reason' => 'refresh_token_reuse_detected',
                ],
            );

            if ($policy->reuse_refresh_token_forbidden && $reusedToken->family_id !== null) {
                $this->tokenRepository->revokeTokenFamily($reusedToken->family_id, 'family_reuse_detected');

                $this->auditLogService->logFailure(
                    logName: AuditLogService::LOG_OAUTH,
                    event: 'oauth.token.family_revoked',
                    description: 'OAuth token family revoked.',
                    subject: $client,
                    causer: $token->user,
                    properties: [
                        'client_id' => $client->id,
                        'client_public_id' => $client->client_id,
                        'token_id' => $reusedToken->id,
                        'user_id' => $reusedToken->user_id,
                        'family_id' => $reusedToken->family_id,
                        'decision' => 'family_revoked',
                        'reason' => 'family_reuse_detected',
                    ],
                );
            }
        });

        $this->logGrantFailure($client, 'refresh_token_reuse_detected');

        throw ValidationException::withMessages([
            'refresh_token' => 'The refresh token is invalid.',
        ]);
    }
}
