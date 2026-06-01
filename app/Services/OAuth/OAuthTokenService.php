<?php

namespace App\Services\OAuth;

use App\Models\AuthorizationCode;
use App\Models\SsoClient;
use App\Models\Token;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Repositories\Contracts\AuthorizationCodeRepositoryInterface;
use App\Repositories\Contracts\TokenRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\TokenFamilyService;
use App\Support\Localization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * OAuth token endpoint üzleti logikáját kezelő szolgáltatás.
 *
 * Ez az osztály felel az authorization code és refresh token grant
 * feldolgozásáért, tokenpárok kiadásáért, token visszavonásért,
 * introspection válaszokért és OIDC userinfo kiszolgálásért.
 *
 * Biztonsági fókusz:
 * - authorization code egyszer használható
 * - refresh token rotation és reuse detection támogatott
 * - scope escalation tiltott token endpointon
 * - tokenek csak hash alapján kereshetők
 * - tokenkiadás és visszavonás auditálható
 *
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
 *     scope: string,
 *     id_token?: string
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
        private readonly AuthorizationCodeRepositoryInterface $authorizationCodeRepository,
        private readonly TokenRepositoryInterface $tokenRepository,
        private readonly AuditLogService $auditLogService,
        private readonly TokenFamilyService $tokenFamilyService,
        private readonly OidcIdTokenService $oidcIdTokenService,
        private readonly OidcUserInfoService $oidcUserInfoService,
        private readonly OidcSubjectService $oidcSubjectService,
        private readonly OidcSigningKeyService $oidcSigningKeyService,
    ) {}

    /**
     * Authorization code grant alapján access/refresh token párt ad ki.
     *
     * A code egyszer használható, klienshez kötött és redirect URI-hoz kötött.
     * A tokenkiadás csak akkor történhet meg, ha a code aktív, a PKCE verifier
     * érvényes, és a code-ban tárolt scope-ok továbbra is engedélyezettek
     * a kliens számára.
     *
     * @param  OAuthTokenPayload  $payload
     * @return TokenPair
     */
    public function exchangeAuthorizationCode(array $payload, ?string $ipAddress, ?string $userAgent): array
    {
        $client = $this->resolveClient((string) $payload['client_id']);
        $this->assertClientAuthentication($client, (string) ($payload['client_secret'] ?? ''), 'authorization_code', $ipAddress, $userAgent);
        $this->assertNoTokenScopeParameter($client, $payload, 'authorization_code');

        $codeHash = hash('sha256', (string) $payload['code']);
        $redirectUri = (string) $payload['redirect_uri'];
        $verifier = trim((string) ($payload['code_verifier'] ?? ''));
        $grantFailureReason = null;
        $grantFailureMessages = null;
        $reusedAuthorizationCode = null;

        $issuedPayload = DB::transaction(function () use (
            $codeHash,
            $redirectUri,
            $verifier,
            $client,
            $ipAddress,
            $userAgent,
            &$grantFailureReason,
            &$grantFailureMessages,
            &$reusedAuthorizationCode
        ): ?array {
            $authorizationCode = $this->authorizationCodeRepository->findWithRelationsByCodeHashForUpdate($codeHash);

            if ($authorizationCode === null) {
                $grantFailureReason = 'invalid_authorization_code';
                $grantFailureMessages = ['code' => 'The provided authorization code is invalid.'];

                return null;
            }

            if ($authorizationCode->sso_client_id !== $client->id) {
                $grantFailureReason = 'authorization_code_client_mismatch';
                $grantFailureMessages = ['code' => 'The authorization code does not belong to this client.'];

                return null;
            }

            if ($authorizationCode->consumed_at !== null || $authorizationCode->revoked_at !== null || $authorizationCode->expires_at->isPast()) {
                $grantFailureReason = $authorizationCode->consumed_at !== null || $authorizationCode->revoked_at !== null
                    ? 'authorization_code_reuse_detected'
                    : 'authorization_code_inactive';
                $grantFailureMessages = ['code' => 'The authorization code is expired, revoked, or already used.'];

                if ($grantFailureReason === 'authorization_code_reuse_detected') {
                    $reusedAuthorizationCode = $authorizationCode;
                }

                return null;
            }

            if (! $this->redirectUriMatcher->matches($client, $redirectUri) || ! hash_equals($authorizationCode->redirect_uri, $redirectUri)) {
                $grantFailureReason = 'redirect_uri_mismatch';
                $grantFailureMessages = ['redirect_uri' => 'The redirect URI does not match the authorization request.'];

                return null;
            }

            if ($verifier === '') {
                $grantFailureReason = 'missing_code_verifier';
                $grantFailureMessages = ['code_verifier' => 'The code verifier field is required.'];

                return null;
            }

            try {
                $this->pkceVerifier->verify($authorizationCode->code_challenge, $authorizationCode->code_challenge_method, $verifier);
            } catch (ValidationException $exception) {
                $grantFailureReason = 'pkce_validation_failed';
                $grantFailureMessages = $exception->errors();

                return null;
            }

            $policy = $this->resolvePolicy($client, $authorizationCode->tokenPolicy);
            if ($this->deniedStoredScopeCodes($client, $authorizationCode->scopes ?? []) !== []) {
                $grantFailureReason = 'scope_grant_no_longer_allowed';
                $grantFailureMessages = ['scope' => 'The requested scope is not allowed for this client.'];

                return null;
            }

            $authorizationCode = $this->authorizationCodeRepository->consume($authorizationCode);

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

            $payload = $issued['payload'];
            $idToken = $this->issueIdTokenIfRequired($authorizationCode);

            if ($idToken !== null) {
                $payload['id_token'] = $idToken;
                $activeSigningKey = $this->oidcSigningKeyService->getActiveSigningKey();

                $this->auditLogService->logSuccess(
                    logName: AuditLogService::LOG_OAUTH,
                    event: 'oauth.id_token.issued_with_kid',
                    description: 'OIDC asymmetric ID token issued with active signing kid.',
                    subject: $client,
                    causer: $authorizationCode->user,
                    properties: [
                        'client_id' => $client->id,
                        'client_public_id' => $client->client_id,
                        'target_user_id' => $authorizationCode->user_id,
                        'scope_contains_openid' => true,
                        'has_nonce' => $authorizationCode->hasIdentityResponseNonce(),
                        'kid' => $activeSigningKey['kid'] ?? null,
                    ],
                );
            }

            return $payload;
        });

        if ($reusedAuthorizationCode instanceof AuthorizationCode) {
            $this->logAuthorizationCodeReuse($reusedAuthorizationCode, $client);
        }

        if ($grantFailureReason !== null && \is_array($grantFailureMessages)) {
            $this->logGrantFailure($client, $grantFailureReason, ['grant_type' => 'authorization_code']);

            throw ValidationException::withMessages($grantFailureMessages);
        }

        if ($issuedPayload === null) {
            $this->logGrantFailure($client, 'invalid_authorization_code', ['grant_type' => 'authorization_code']);

            throw ValidationException::withMessages([
                'code' => 'The provided authorization code is invalid.',
            ]);
        }

        return $issuedPayload;
    }

    /**
     * Refresh token grant alapján új access/refresh token párt ad ki.
     *
     * A grant az eredeti refresh token scope-jait örökíti tovább, ezért
     * token endpointon nem engedünk új scope paramétert. Rotáció esetén
     * a régi refresh token lezárásra kerül, reuse gyanú esetén pedig a
     * teljes tokencsalád incidenskezelés alá kerülhet.
     *
     * @param  OAuthTokenPayload  $payload
     * @return TokenPair
     */
    public function refreshAccessToken(array $payload, ?string $ipAddress, ?string $userAgent): array
    {
        $client = $this->resolveClient((string) $payload['client_id']);
        $this->assertClientAuthentication($client, (string) ($payload['client_secret'] ?? ''), 'refresh_token', $ipAddress, $userAgent);
        $this->assertNoTokenScopeParameter($client, $payload, 'refresh_token');

        $refreshTokenHash = hash('sha256', (string) $payload['refresh_token']);
        $reusedToken = null;
        $grantFailureReason = null;
        $grantFailureMessages = null;

        $issuedPayload = DB::transaction(function () use ($refreshTokenHash, $client, $ipAddress, $userAgent, &$reusedToken, &$grantFailureReason, &$grantFailureMessages): ?array {
            $token = $this->tokenRepository->findTokenWithRelationsByRefreshHashForUpdate($refreshTokenHash);

            if ($token === null || $token->sso_client_id !== $client->id) {
                $grantFailureReason = 'invalid_refresh_token';
                $grantFailureMessages = ['refresh_token' => 'The provided refresh token is invalid.'];

                return null;
            }

            if ($token->refresh_token_expires_at === null || $token->refresh_token_expires_at->isPast()) {
                $grantFailureReason = 'refresh_token_inactive';
                $grantFailureMessages = ['refresh_token' => 'The refresh token is expired or revoked.'];

                return null;
            }

            $policy = $this->resolvePolicy($client, $token->tokenPolicy);

            if (! $token->isRefreshTokenActive()) {
                $reusedToken = $token;

                return null;
            }

            if ($this->deniedStoredScopeCodes($client, $token->scopes ?? []) !== []) {
                $grantFailureReason = 'scope_grant_no_longer_allowed';
                $grantFailureMessages = ['scope' => 'The requested scope is not allowed for this client.'];

                return null;
            }

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

        if ($reusedToken instanceof Token) {
            $this->handleRefreshTokenReuse($reusedToken, $client);
        }

        if ($grantFailureReason !== null && \is_array($grantFailureMessages)) {
            $this->logGrantFailure($client, $grantFailureReason, ['grant_type' => 'refresh_token']);

            throw ValidationException::withMessages($grantFailureMessages);
        }

        if ($issuedPayload === null) {
            $this->logGrantFailure($client, 'invalid_refresh_token', ['grant_type' => 'refresh_token']);

            throw ValidationException::withMessages([
                'refresh_token' => 'The refresh token is invalid.',
            ]);
        }

        return $issuedPayload;
    }

    /**
     * Megakadályozza a scope paraméter használatát token endpointon.
     *
     * Authorization code grantnél a scope a felhasznált code-ból,
     * refresh token grantnél pedig a tárolt refresh tokenből származik.
     * Itt új scope elfogadása scope escalation vagy félreérthető
     * jogosultsági döntés lenne.
     *
     * @param  OAuthTokenPayload  $payload
     */
    private function assertNoTokenScopeParameter(SsoClient $client, array $payload, string $grantType): void
    {
        $requestedScope = trim((string) ($payload['scope'] ?? ''));

        if ($requestedScope === '') {
            return;
        }

        $this->logGrantFailure($client, 'scope_parameter_not_allowed', [
            'grant_type' => $grantType,
            'affected_count' => \count($this->parseScopeCodes($requestedScope)),
        ]);

        throw ValidationException::withMessages([
            'scope' => 'The scope parameter is not supported for this grant type.',
        ]);
    }

    /**
     * Megkeresi azokat a tárolt scope-okat, amelyek már nem engedélyezettek a klienshez.
     *
     * Tokenkiadás előtt újraellenőrizzük a korábban eltárolt scope-listát,
     * hogy egy időközben visszavont kliens-scope kapcsolatból ne lehessen
     * új tokenpárt kiadni.
     *
     * @param  array<int, string>  $scopeCodes
     * @return array<int, string>
     */
    private function deniedStoredScopeCodes(SsoClient $client, array $scopeCodes): array
    {
        $normalizedScopes = $this->normalizeScopeCodes($scopeCodes);
        $allowedScopes = $client->fresh(['scopes'])?->normalizedScopeCodes() ?? [];

        return array_values(array_diff($normalizedScopes, $allowedScopes));
    }

    /**
     * Space-delimited OAuth scope stringből normalizált scope listát készít.
     *
     * A helper audit és hibakezelési célokra is használható, hogy ne nyers,
     * duplikált vagy üres scope részletekkel dolgozzunk.
     *
     * @return array<int, string>
     */
    private function parseScopeCodes(string $scopeString): array
    {
        return $this->normalizeScopeCodes(preg_split('/\s+/', $scopeString) ?: []);
    }

    /**
     * Egységesíti a scope kódokat tokenkiadási és audit döntésekhez.
     *
     * A scope-okból eltávolítja az üres értékeket és a duplikációkat,
     * így a jogosultsági összehasonlítások determinisztikusak maradnak.
     *
     * @param  array<int, mixed>  $scopeCodes
     * @return array<int, string>
     */
    private function normalizeScopeCodes(array $scopeCodes): array
    {
        return collect($scopeCodes)
            ->map(static fn (mixed $scope): string => trim((string) $scope))
            ->filter(static fn (string $scope): bool => $scope !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Aktív OAuth klienst keres a publikus client_id alapján.
     *
     * A kliensfeloldás minden token endpoint művelet belépési pontja.
     * Inaktív vagy ismeretlen kliens esetén auditált grant megszakítás történik.
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
     * Ellenőrzi a klienshitelesítést a kliens típusának megfelelően.
     *
     * Public kliensnél nincs secret elvárás. Confidential kliensnél viszont
     * aktív secret szükséges, és csak a jelenleg érvényes secret készlet
     * valamelyikével hitelesíthető a token endpoint művelet.
     *
     * @throws AuthenticationException
     */
    private function assertClientAuthentication(
        SsoClient $client,
        string $clientSecret,
        ?string $grantType = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        if ($client->isPublic()) {
            return;
        }

        $providedSecret = trim($clientSecret);

        if ($providedSecret === '') {
            $this->rejectClientAuthentication($client, 'confidential_client_secret_missing', $grantType, $ipAddress, $userAgent);
        }

        if (! $client->hasActiveSecrets()) {
            $this->rejectClientAuthentication($client, 'confidential_client_no_active_secret', $grantType, $ipAddress, $userAgent);
        }

        if ($this->verifyActiveClientSecret($client, $providedSecret)) {
            return;
        }

        $this->rejectClientAuthentication($client, 'confidential_client_invalid_secret', $grantType, $ipAddress, $userAgent);
    }

    /**
     * Ellenőrzi a beküldött kliens secretet az aktív secret készlettel szemben.
     *
     * A secret nyers értéke nincs tárolva, ezért kizárólag hash ellenőrzés
     * történik. Több aktív secret támogatása lehetővé teszi a biztonságos
     * secret rotációt átmeneti leállás nélkül.
     */
    private function verifyActiveClientSecret(SsoClient $client, string $providedSecret): bool
    {
        foreach ($client->activeSecrets()->get() as $secret) {
            if (Hash::check($providedSecret, $secret->secret_hash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Elutasítja a sikertelen confidential client hitelesítést.
     *
     * A hiba auditálva van grant, IP és user agent kontextussal,
     * de a kliens felé egységes hitelesítési hiba tér vissza, hogy
     * ne szivárogjon ki, melyik hitelesítési feltétel bukott el.
     */
    private function rejectClientAuthentication(
        SsoClient $client,
        string $reason,
        ?string $grantType,
        ?string $ipAddress,
        ?string $userAgent,
    ): never {
        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.client_auth.failed',
            description: 'OAuth client authentication failed.',
            subject: $client,
            properties: [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'grant_type' => $grantType,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'reason' => $reason,
            ],
        );

        throw ValidationException::withMessages([
            'client' => [Localization::translate('api.oauth.invalid_client_credentials')],
        ])->status(401);
    }

    /**
     * Meghatározza a klienshez érvényes aktív token policy-t.
     *
     * Először a granthez már betöltött policy-t használja, ha az aktív.
     * Ennek hiányában a klienshez rendelt policy vagy az aktív default policy
     * alapján dől el a tokenek élettartama, rotációja és egyéb szabálya.
     *
     * @return TokenPolicy
     */
    private function resolvePolicy(SsoClient $client, ?TokenPolicy $policy = null): TokenPolicy
    {
        if ($policy instanceof TokenPolicy && $policy->is_active) {
            return $this->assertPolicyAllowedForClientType($client, $policy);
        }

        $resolved = TokenPolicy::query()
            ->when($client->token_policy_id !== null, fn ($query) => $query->whereKey($client->token_policy_id))
            ->when($client->token_policy_id === null, fn ($query) => $query->where('is_default', true))
            ->where('is_active', true)
            ->first();

        if ($resolved === null) {
            throw ValidationException::withMessages(['client_id' => 'No active token policy is available for this client.']);
        }

        return $this->assertPolicyAllowedForClientType($client, $resolved);
    }

    private function assertPolicyAllowedForClientType(SsoClient $client, TokenPolicy $policy): TokenPolicy
    {
        if ($client->isPublic() && ! $policy->pkce_required) {
            $this->logGrantFailure($client, 'public_client_policy_requires_pkce', [
                'policy_id' => $policy->id,
                'client_type' => $client->client_type,
            ]);

            throw ValidationException::withMessages([
                'client_id' => 'Public clients must use a token policy where PKCE is required.',
            ]);
        }

        return $policy;
    }

    /**
     * Új access/refresh token párt állít elő és ment el.
     *
     * A nyers tokenek csak a válasz payloadban jelennek meg, adatbázisban
     * kizárólag hash formában tárolódnak. A family_id és parent_token_id
     * biztosítja a refresh token lánc auditálhatóságát.
     *
     * @param  array<int, string>  $scopes
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
     * OIDC kérés esetén ID tokent állít elő.
     *
     * ID token csak akkor jár a tokenválaszhoz, ha az authorization code
     * olyan OIDC folyamatból származik, amely nonce validációt igényel.
     */
    private function issueIdTokenIfRequired(AuthorizationCode $authorizationCode): ?string
    {
        if (! $authorizationCode->requiresIdentityNonceValidation()) {
            return null;
        }

        return $this->oidcIdTokenService->issueForAuthorizationCode($authorizationCode);
    }

    /**
     * Visszavon egy klienshez tartozó access vagy refresh tokent.
     *
     * A művelet idempotens: ha a token nem található, nem aktív, vagy nem
     * a kérő klienshez tartozik, nem szivárogtatunk információt a token
     * létezéséről. Találat esetén a visszavonás auditálva történik.
     *
     * @param  OAuthTokenPayload  $payload
     */
    public function revokeToken(array $payload): void
    {
        $client = $this->resolveClient((string) $payload['client_id']);
        $this->assertClientAuthentication($client, (string) ($payload['client_secret'] ?? ''), null, null, null);

        $plainToken = (string) $payload['token'];
        $tokenHash = hash('sha256', $plainToken);
        $tokenTypeHint = $this->normalizeTokenTypeHint($payload['token_type_hint'] ?? null);
        $reason = \is_string($payload['reason'] ?? null) && trim((string) $payload['reason']) !== ''
            ? trim((string) $payload['reason'])
            : null;

        DB::transaction(function () use ($client, $tokenHash, $tokenTypeHint, $reason): void {
            if ($tokenTypeHint === 'access_token') {
                $token = $this->tokenRepository->findActiveAccessTokenByHash($tokenHash);

                if ($token === null || (int) $token->sso_client_id !== (int) $client->id) {
                    return;
                }

                $this->tokenRepository->revokeAccessToken($token, \is_string($reason) ? $reason : null);

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

                $this->tokenRepository->revokeRefreshToken($token, \is_string($reason) ? $reason : null);

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
                $this->tokenRepository->revokeAccessToken($accessToken, \is_string($reason) ? $reason : null);

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
                $this->tokenRepository->revokeRefreshToken($refreshToken, \is_string($reason) ? $reason : null);

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
     * Token introspection választ ad a kérő kliens számára.
     *
     * Csak a saját klienshez tartozó aktív token adatait tekinti aktívnak.
     * Ismeretlen, lejárt, visszavont vagy más klienshez tartozó token esetén
     * szabványos inaktív választ ad vissza.
     *
     * @param  OAuthTokenPayload  $payload
     * @return IntrospectionResponse
     */
    public function introspectToken(array $payload): array
    {
        $client = $this->resolveClient((string) $payload['client_id']);
        $this->assertClientAuthentication($client, (string) ($payload['client_secret'] ?? ''), null, null, null);

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
     * OIDC userinfo claim-eket ad vissza érvényes Bearer access token alapján.
     *
     * A userinfo endpoint csak openid scope-pal használható. Sikeres lekéréskor
     * frissül a token használati metaadata, és audit esemény rögzíti, melyik
     * kliens mely scope-okkal kért felhasználói adatokat.
     *
     * @return UserInfoClaims
     */
    public function getUserInfo(?string $plainAccessToken, ?string $ipAddress, ?string $userAgent): array
    {
        $token = $this->resolveUserInfoAccessToken($plainAccessToken);

        if (! \in_array('openid', $token->scopes ?? [], true)) {
            throw new AuthorizationException('The access token does not grant the openid scope.');
        }

        $claims = $this->oidcUserInfoService->claimsForToken($token);

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
     * Normalizálja az opcionális token type hint értéket.
     *
     * A hint csak keresési optimalizáció és nem bizalmi forrás:
     * ha ismeretlen vagy hiányzik, a rendszer mindkét támogatott token
     * típust megpróbálhatja biztonságosan feloldani.
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
     * Eldönti, hogy a token introspection melyik token típus alapján történjen.
     *
     * Ha a kliens küld type hintet, azt használjuk elsődlegesen.
     * Hint nélkül access tokenként próbáljuk először, majd refresh tokenként,
     * miközben csak aktív és klienshez tartozó token adhat aktív választ.
     *
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
     * Access token introspection belső feloldása.
     *
     * Más klienshez tartozó, ismeretlen vagy inaktív access tokenről nem adunk
     * részletes információt, csak szabványos inactive választ.
     *
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
     * Refresh token introspection belső feloldása.
     *
     * Refresh token esetén is csak a tulajdonos kliens kap aktív választ,
     * így az endpoint nem használható tokenlétezés felderítésére más kliensekhez.
     *
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
     * Eldönti, hogy a tárolt access token még használható-e.
     *
     * Az aktív állapot a token modell központi életciklus-szabályaira épül,
     * így az introspection és userinfo ugyanazt az érvényességi logikát használja.
     */
    private function isAccessTokenActive(Token $token): bool
    {
        return $token->isAccessTokenActive();
    }

    /**
     * Eldönti, hogy a tárolt refresh token még használható-e.
     *
     * A refresh token aktív állapota figyelembe veszi a lejáratot,
     * visszavonást, rotációt, tokencsalád visszavonást és incidensjelölést.
     */
    private function isRefreshTokenActive(Token $token): bool
    {
        return $token->isRefreshTokenActive();
    }

    /**
     * OAuth introspection kompatibilis aktív token választ formáz.
     *
     * Csak olyan adatok kerülnek visszaadásra, amelyekre a kérő kliens
     * jogosult és amelyek szükségesek az erőforrás-szerver döntéséhez.
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
            'sub' => $this->oidcSubjectService->forUserId($token->user_id),
            'exp' => $expiresAt?->getTimestamp(),
        ];
    }

    /**
     * Szabványos inaktív introspection választ ad.
     *
     * Ugyanazt a választ használjuk ismeretlen, más klienshez tartozó,
     * lejárt vagy visszavont tokenre, hogy ne szivárogjon tokenállapot.
     *
     * @return IntrospectionResponse
     */
    private function inactiveIntrospectionResponse(): array
    {
        return ['active' => false];
    }

    /**
     * Bearer access tokent felold userinfo használathoz.
     *
     * A nyers token csak hash-elés után kerül keresésre. A metódus kizárólag
     * aktív access tokent fogad el, és betölti a kliens/felhasználó kontextust
     * a claim előállításhoz és auditáláshoz.
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
     * Authorization code újrafelhasználási kísérletet auditál.
     *
     * Az authorization code egyszer használható credential. Újrafelhasználása
     * klienshibára, race conditionre vagy visszaélési kísérletre utalhat,
     * ezért külön biztonsági eseményként kerül rögzítésre.
     */
    private function logAuthorizationCodeReuse(AuthorizationCode $authorizationCode, SsoClient $client): void
    {
        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.authorization_code.reuse_detected',
            description: 'OAuth authorization code reuse detected.',
            subject: $client,
            causer: $authorizationCode->user,
            properties: [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'user_id' => $authorizationCode->user_id,
                'authorization_code_id' => $authorizationCode->id,
                'grant_type' => 'authorization_code',
                'reason' => 'authorization_code_reuse_detected',
            ],
        );
    }

    /**
     * Token grant sikertelenséget rögzít audit és visszaélésvizsgálat céljából.
     *
     * A helper egységesíti a grant hibák eseménynevét és alap property-it,
     * miközben a hívó metódus grant-specifikus részleteket is hozzáadhat.
     *
     * @param  array<string, mixed>  $properties
     */
    private function logGrantFailure(SsoClient $client, string $reason, array $properties = []): void
    {
        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.token.grant_failed',
            description: 'OAuth token grant failed.',
            subject: $client,
            properties: [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'client_type' => $client->client_type,
                'reason' => $reason,
                ...$properties,
            ],
        );
    }

    /**
     * Külön audit eseményeket rögzít az access és refresh token kiadásáról.
     *
     * A tokenpár egy válaszban keletkezik, de audit szempontból hasznos
     * külön látni az access token és refresh token életciklusának kezdetét.
     *
     * @param  array<int, string>  $scopeCodes
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

    /**
     * Refresh token reuse észlelésekor incidenskezelést indít.
     *
     * Ha a refresh token tokencsaládhoz tartozik, a család gyanúsnak jelölhető
     * és visszavonható. A kliens felé szándékosan általános invalid token hiba
     * tér vissza, hogy ne legyen kihasználható tokenállapot-felderítésre.
     */
    private function handleRefreshTokenReuse(Token $token, SsoClient $client): never
    {
        if ($token->family_id !== null) {
            $this->tokenFamilyService->handleSuspiciousRefreshReuse($token, [
                'incident_reason' => 'refresh_reuse_detected',
                'trigger' => 'automatic_reuse_response',
            ]);
        }

        $this->logGrantFailure($client, 'refresh_token_reuse_detected');

        throw ValidationException::withMessages([
            'refresh_token' => 'The refresh token is invalid.',
        ]);
    }
}
