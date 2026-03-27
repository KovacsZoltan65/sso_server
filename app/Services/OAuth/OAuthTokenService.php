<?php

namespace App\Services\OAuth;

use App\Models\AuthorizationCode;
use App\Models\Token;
use App\Models\TokenPolicy;
use App\Models\SsoClient;
use App\Models\User;
use App\Repositories\Contracts\TokenRepositoryInterface;
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
 *     token_type_hint?: string|null
 * }
 * @phpstan-type TokenPair array{
 *     token_type: string,
 *     access_token: string,
 *     refresh_token: string,
 *     expires_in: int,
 *     refresh_token_expires_in: int,
 *     scope: string
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
            throw ValidationException::withMessages(['code' => 'The provided authorization code is invalid.']);
        }

        if ($authorizationCode->sso_client_id !== $client->id) {
            throw ValidationException::withMessages(['code' => 'The authorization code does not belong to this client.']);
        }

        if ($authorizationCode->consumed_at !== null || $authorizationCode->revoked_at !== null || $authorizationCode->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'The authorization code is expired, revoked, or already used.']);
        }

        $redirectUri = trim((string) $payload['redirect_uri']);
        if (! $this->redirectUriMatcher->matches($client, $redirectUri) || ! hash_equals($authorizationCode->redirect_uri, $redirectUri)) {
            throw ValidationException::withMessages(['redirect_uri' => 'The redirect URI does not match the authorization request.']);
        }

        $verifier = trim((string) ($payload['code_verifier'] ?? ''));
        if ($verifier === '') {
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
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            activity('oauth')
                ->performedOn($client)
                ->causedBy($authorizationCode->user)
                ->event('oauth.token.issued')
                ->withProperties(['grant_type' => 'authorization_code'])
                ->log('OAuth token pair issued from authorization code.');

            return $issued;
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
        $token = Token::query()
            ->with('tokenPolicy')
            ->where('refresh_token_hash', hash('sha256', $plainRefreshToken))
            ->first();

        if ($token === null || $token->sso_client_id !== $client->id) {
            throw ValidationException::withMessages(['refresh_token' => 'The provided refresh token is invalid.']);
        }

        if ($token->refresh_token_revoked_at !== null || $token->refresh_token_expires_at === null || $token->refresh_token_expires_at->isPast()) {
            throw ValidationException::withMessages(['refresh_token' => 'The refresh token is expired or revoked.']);
        }

        $policy = $this->resolvePolicy($client, $token->tokenPolicy);

        return DB::transaction(function () use ($token, $client, $policy, $ipAddress, $userAgent): array {
            if ($policy->refresh_token_rotation_enabled || $policy->reuse_refresh_token_forbidden) {
                $token->forceFill([
                    'refresh_token_revoked_at' => now(),
                ])->save();
            }

            $issued = $this->issueTokenPair(
                client: $client,
                userId: $token->user_id,
                policy: $policy,
                scopes: $token->scopes ?? [],
                authorizationCodeId: null,
                parentTokenId: $token->id,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            activity('oauth')
                ->performedOn($client)
                ->causedBy($token->user)
                ->event('oauth.token.refreshed')
                ->withProperties(['parent_token_id' => $token->id])
                ->log('OAuth access token refreshed.');

            return $issued;
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
            activity('oauth')
                ->event('oauth.client.authentication_failed')
                ->withProperties([
                    'client_id' => $clientId,
                    'reason' => 'invalid_client',
                ])
                ->log('OAuth client authentication failed.');

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

        activity('oauth')
            ->performedOn($client)
            ->event('oauth.client.authentication_failed')
            ->withProperties([
                'client_id' => $client->client_id,
                'reason' => 'invalid_client_secret',
            ])
            ->log('OAuth client authentication failed.');

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
     * @return TokenPair
     */
    private function issueTokenPair(
        SsoClient $client,
        int $userId,
        TokenPolicy $policy,
        array $scopes,
        ?int $authorizationCodeId,
        ?int $parentTokenId,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        $plainAccessToken = Str::random(80);
        $plainRefreshToken = Str::random(80);

        $accessExpiresAt = now()->addMinutes($policy->access_token_ttl_minutes);
        $refreshExpiresAt = now()->addMinutes($policy->refresh_token_ttl_minutes);

        Token::query()->create([
            'sso_client_id' => $client->id,
            'user_id' => $userId,
            'token_policy_id' => $policy->id,
            'authorization_code_id' => $authorizationCodeId,
            'parent_token_id' => $parentTokenId,
            'access_token_hash' => hash('sha256', $plainAccessToken),
            'refresh_token_hash' => hash('sha256', $plainRefreshToken),
            'scopes' => array_values($scopes),
            'access_token_expires_at' => $accessExpiresAt,
            'refresh_token_expires_at' => $refreshExpiresAt,
            'issued_from_ip' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        return [
            'token_type' => 'Bearer',
            'access_token' => $plainAccessToken,
            'refresh_token' => $plainRefreshToken,
            'expires_in' => now()->diffInSeconds($accessExpiresAt),
            'refresh_token_expires_in' => now()->diffInSeconds($refreshExpiresAt),
            'scope' => implode(' ', $scopes),
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

        DB::transaction(function () use ($client, $tokenHash, $tokenTypeHint): void {
            if ($tokenTypeHint === 'access_token') {
                $token = $this->tokenRepository->findActiveAccessTokenByHash($tokenHash);

                if ($token === null || (int) $token->sso_client_id !== (int) $client->id) {
                    return;
                }

                $this->tokenRepository->revokeAccessToken($token);

                activity('oauth')
                    ->performedOn($client)
                    ->event('oauth.token.revoked')
                    ->withProperties([
                        'token_kind' => 'access_token',
                        'token_id' => $token->id,
                    ])
                    ->log('OAuth access token revoked.');

                return;
            }

            if ($tokenTypeHint === 'refresh_token') {
                $token = $this->tokenRepository->findActiveRefreshTokenByHash($tokenHash);

                if ($token === null || (int) $token->sso_client_id !== (int) $client->id) {
                    return;
                }

                $this->tokenRepository->revokeRefreshToken($token);

                activity('oauth')
                    ->performedOn($client)
                    ->event('oauth.token.revoked')
                    ->withProperties([
                        'token_kind' => 'refresh_token',
                        'token_id' => $token->id,
                    ])
                    ->log('OAuth refresh token revoked.');

                return;
            }

            $accessToken = $this->tokenRepository->findActiveAccessTokenByHash($tokenHash);

            if ($accessToken !== null && (int) $accessToken->sso_client_id === (int) $client->id) {
                $this->tokenRepository->revokeAccessToken($accessToken);

                activity('oauth')
                    ->performedOn($client)
                    ->event('oauth.token.revoked')
                    ->withProperties([
                        'token_kind' => 'access_token',
                        'token_id' => $accessToken->id,
                    ])
                    ->log('OAuth access token revoked.');

                return;
            }

            $refreshToken = $this->tokenRepository->findActiveRefreshTokenByHash($tokenHash);

            if ($refreshToken !== null && (int) $refreshToken->sso_client_id === (int) $client->id) {
                $this->tokenRepository->revokeRefreshToken($refreshToken);

                activity('oauth')
                    ->performedOn($client)
                    ->event('oauth.token.revoked')
                    ->withProperties([
                        'token_kind' => 'refresh_token',
                        'token_id' => $refreshToken->id,
                    ])
                    ->log('OAuth refresh token revoked.');
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

        activity('oauth')
            ->performedOn($client)
            ->event('oauth.token.introspected')
            ->withProperties([
                'active' => $response['active'],
                'token_type_hint' => $tokenTypeHint,
                'resolved_token_type' => $response['token_type'] ?? null,
            ])
            ->log('OAuth token introspection completed.');

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

        activity('oauth')
            ->performedOn($token->client)
            ->causedBy($token->user)
            ->event('oauth.userinfo.requested')
            ->withProperties([
                'scope' => implode(' ', $token->scopes ?? []),
            ])
            ->log('OAuth user info retrieved.');

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
        return $token->access_token_revoked_at === null
            && $token->access_token_expires_at !== null
            && ! $token->access_token_expires_at->isPast();
    }

    /**
     * Determine whether the stored refresh token is still active and usable.
     */
    private function isRefreshTokenActive(Token $token): bool
    {
        return $token->refresh_token_revoked_at === null
            && $token->refresh_token_expires_at !== null
            && ! $token->refresh_token_expires_at->isPast();
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
}
