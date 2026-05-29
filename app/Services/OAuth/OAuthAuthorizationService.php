<?php

namespace App\Services\OAuth;

use App\Data\OAuth\OAuthRememberedConsentDecisionResult;
use App\Data\OAuth\OAuthTrustDecisionResult;
use App\Exceptions\OAuth\OAuthConsentContextNotFoundException;
use App\Models\AuthorizationCode;
use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\ClientUserAccessService;
use App\Support\Localization;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type AuthorizationPayload array{
 *     client_id: string,
 *     redirect_uri: string,
 *     scope?: string|null,
 *     state?: string|null,
 *     nonce?: string|null,
 *     code_challenge?: string|null,
 *     code_challenge_method?: string|null
 * }
 * @phpstan-type AuthorizationApproval array{
 *     redirect_url: string,
 *     code: string|null,
 *     client: SsoClient,
 *     scopes: array<int, string>
 * }
 * @phpstan-type ConsentScopeView array{
 *     code: string,
 *     name: string,
 *     description: string|null
 * }
 * @phpstan-type ConsentPreparationResult array{
 *     type: 'consent',
 *     consentToken: string,
 *     client: array{
 *         name: string,
 *         description: string,
 *         originHost: string|null,
 *         returnPath: string|null,
 *         trustLabel: string,
 *         trustDescription: string
 *     },
 *     scopes: array<int, ConsentScopeView>,
 *     summary: array{title: string, description: string}
 * }
 * @phpstan-type AuthorizationRedirectResult array{
 *     type: 'redirect',
 *     redirect_url: string
 * }
 * @phpstan-type ConsentDecisionResult array{
 *     redirect_url: string
 * }
 */
class OAuthAuthorizationService
{
    public function __construct(
        private readonly RedirectUriMatcher $redirectUriMatcher,
        private readonly ClientUserAccessService $clientUserAccessService,
        private readonly AuditLogService $auditLogService,
        private readonly OAuthConsentContextService $consentContextService,
        private readonly OAuthTrustDecisionService $trustDecisionService,
        private readonly OAuthRememberedConsentService $rememberedConsentService,
        private readonly OidcFrontChannelLogoutService $frontChannelLogoutService,
        private readonly OidcSessionService $oidcSessionService,
    ) {}

    /**
     * Prepare a consent screen for a validated authorize request.
     *
     * @param  AuthorizationPayload  $payload
     * @return ConsentPreparationResult|AuthorizationRedirectResult
     */
    public function prepareConsent(User $user, array $payload): array
    {
        $client = $this->resolveActiveClientOrFail($user, $payload);
        $redirectUri = trim((string) $payload['redirect_uri']);

        $this->assertRedirectUriMatches($user, $client, $redirectUri);

        $requestedScopes = $this->resolveScopes($client, (string) ($payload['scope'] ?? ''), $user);
        $policy = $this->resolvePolicy($client);
        $nonce = trim((string) ($payload['nonce'] ?? ''));

        $codeChallenge = trim((string) ($payload['code_challenge'] ?? ''));
        $codeChallengeMethod = trim((string) ($payload['code_challenge_method'] ?? ''));

        $this->assertPkceRequirements($user, $client, $policy, $codeChallenge, $codeChallengeMethod);
        $this->logNonceAccepted($user, $client, $requestedScopes, $nonce);

        $accessDecision = $this->clientUserAccessService->evaluateUserAccess($user, $client);

        if (! $accessDecision['allowed']) {
            $this->logAuthorizationFailure(
                user: $user,
                event: 'oauth.authorization.denied',
                message: Localization::translate('api.oauth.authorization_denied'),
                client: $client,
                properties: [
                    'reason' => (string) $accessDecision['reason'],
                    'decision' => (string) $accessDecision['decision'],
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'target_user_id' => $user->id,
                    'allowed_from' => $accessDecision['allowed_from'],
                    'allowed_until' => $accessDecision['allowed_until'],
                    'status' => 'forbidden',
                ],
            );

            $accessDeniedDescription = Localization::translate('api.oauth.access_denied');

            return [
                'type' => 'redirect',
                'redirect_url' => $this->buildAuthorizationErrorRedirect(
                    redirectUri: $redirectUri,
                    error: 'access_denied',
                    description: $accessDeniedDescription,
                    state: Arr::get($payload, 'state'),
                ),
            ];
        }

        $trustDecision = $this->trustDecisionService->decideForAuthorization(
            client: $client,
            requestedScopes: $requestedScopes,
            responseType: (string) $payload['response_type'],
        );

        $this->logTrustDecision($user, $client, $trustDecision);

        if ($trustDecision->shouldSkipConsent()) {
            return [
                'type' => 'redirect',
                'redirect_url' => $this->issueAuthorizationCode(
                    client: $client,
                    user: $user,
                    policy: $policy,
                    redirectUri: $redirectUri,
                    requestedScopes: $requestedScopes,
                    state: Arr::get($payload, 'state'),
                    nonce: $nonce !== '' ? $nonce : null,
                    codeChallenge: $codeChallenge,
                    codeChallengeMethod: $codeChallengeMethod,
                )['redirect_url'],
            ];
        }

        if ($trustDecision->shouldDenyAuthorization()) {
            return [
                'type' => 'redirect',
                'redirect_url' => $this->buildAuthorizationErrorRedirect(
                    redirectUri: $redirectUri,
                    error: 'access_denied',
                    description: Localization::translate('api.oauth.access_denied'),
                    state: Arr::get($payload, 'state'),
                ),
            ];
        }

        $rememberedConsentDecision = $this->rememberedConsentService->evaluateReusableConsent(
            user: $user,
            client: $client,
            scopeCodes: $requestedScopes,
            redirectUri: $redirectUri,
        );

        $this->logRememberedConsentDecision($user, $client, $requestedScopes, $rememberedConsentDecision);

        if ($rememberedConsentDecision->shouldReuse) {
            return [
                'type' => 'redirect',
                'redirect_url' => $this->issueAuthorizationCode(
                    client: $client,
                    user: $user,
                    policy: $policy,
                    redirectUri: $redirectUri,
                    requestedScopes: $requestedScopes,
                    state: Arr::get($payload, 'state'),
                    nonce: $nonce !== '' ? $nonce : null,
                    codeChallenge: $codeChallenge,
                    codeChallengeMethod: $codeChallengeMethod,
                )['redirect_url'],
            ];
        }

        $context = $this->consentContextService->storeContext(
            user: $user,
            client: $client,
            payload: $payload,
            requestedScopes: $requestedScopes,
        );

        return [
            'type' => 'consent',
            'consentToken' => $context->consentToken,
            'client' => $this->buildConsentClientView($client, $redirectUri),
            'scopes' => $this->buildConsentScopeView($client, $requestedScopes),
            'summary' => [
                'title' => \sprintf('%s is requesting access to your account.', $this->resolveClientDisplayName($client)),
                'description' => Localization::translate('api.oauth.consent.review_description'),
            ],
        ];
    }

    /**
     * Approve an authorization request and issue a redirectable authorization code.
     *
     * @param  AuthorizationPayload  $payload
     * @return AuthorizationApproval
     */
    public function approve(User $user, array $payload): array
    {
        $client = $this->resolveActiveClientOrFail($user, $payload);
        $redirectUri = trim((string) $payload['redirect_uri']);
        $this->assertRedirectUriMatches($user, $client, $redirectUri);
        $requestedScopes = $this->resolveScopes($client, (string) ($payload['scope'] ?? ''), $user);
        $policy = $this->resolvePolicy($client);
        $nonce = trim((string) ($payload['nonce'] ?? ''));

        $codeChallenge = trim((string) ($payload['code_challenge'] ?? ''));
        $codeChallengeMethod = trim((string) ($payload['code_challenge_method'] ?? ''));
        $this->assertPkceRequirements($user, $client, $policy, $codeChallenge, $codeChallengeMethod);
        $this->logNonceAccepted($user, $client, $requestedScopes, $nonce);

        $accessDecision = $this->clientUserAccessService->evaluateUserAccess($user, $client);

        if (! $accessDecision['allowed']) {
            $this->logAuthorizationFailure(
                user: $user,
                event: 'oauth.authorization.denied',
                message: Localization::translate('api.oauth.authorization_denied'),
                client: $client,
                properties: [
                    'reason' => (string) $accessDecision['reason'],
                    'decision' => (string) $accessDecision['decision'],
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'target_user_id' => $user->id,
                    'allowed_from' => $accessDecision['allowed_from'],
                    'allowed_until' => $accessDecision['allowed_until'],
                    'status' => 'forbidden',
                ],
            );

            return [
                'redirect_url' => $this->buildAuthorizationErrorRedirect(
                    redirectUri: $redirectUri,
                    error: 'access_denied',
                    description: Localization::translate('api.oauth.access_denied'),
                    state: Arr::get($payload, 'state'),
                ),
                'code' => null,
                'client' => $client,
                'scopes' => $requestedScopes,
            ];
        }

        return $this->issueAuthorizationCode(
            client: $client,
            user: $user,
            policy: $policy,
            redirectUri: $redirectUri,
            requestedScopes: $requestedScopes,
            state: Arr::get($payload, 'state'),
            nonce: $nonce !== '' ? $nonce : null,
            codeChallenge: $codeChallenge,
            codeChallengeMethod: $codeChallengeMethod,
        );
    }

    /**
     * Approve a consent decision using the server-side consent context token.
     *
     * @return AuthorizationApproval
     */
    public function approveConsent(
        User $user,
        string $consentToken,
        bool $rememberConsent = false,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        try {
            $context = $this->consentContextService->getContextByToken($consentToken);
        } catch (OAuthConsentContextNotFoundException) {
            throw ValidationException::withMessages([
                'consent_token' => Localization::translate('api.oauth.consent.token_invalid'),
            ]);
        }

        if ($context->userId !== $user->id) {
            $this->consentContextService->invalidateContext($consentToken);

            throw ValidationException::withMessages([
                'consent_token' => Localization::translate('api.oauth.consent.token_user_mismatch'),
            ]);
        }

        /** @var SsoClient|null $client */
        $client = SsoClient::query()
            ->with(['tokenPolicy'])
            ->whereKey($context->clientDbId)
            ->where('client_id', $context->clientId)
            ->where('is_active', true)
            ->first();

        if (! $client instanceof SsoClient) {
            $this->consentContextService->invalidateContext($consentToken);

            throw ValidationException::withMessages([
                'consent_token' => Localization::translate('api.oauth.consent.token_client_invalid'),
            ]);
        }

        $policy = $this->resolvePolicy($client);

        $result = DB::transaction(function () use ($client, $user, $policy, $context, $rememberConsent, $ipAddress, $userAgent): array {
            $result = $this->issueAuthorizationCode(
                client: $client,
                user: $user,
                policy: $policy,
                redirectUri: $context->redirectUri,
                requestedScopes: $context->requestedScopes,
                state: $context->state,
                nonce: $context->nonce,
                codeChallenge: $context->codeChallenge ?? '',
                codeChallengeMethod: $context->codeChallengeMethod ?? '',
            );

            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_OAUTH,
                event: 'oauth.consent.approved',
                description: 'OAuth consent approved.',
                subject: $client,
                causer: $user,
                properties: [
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'scope_codes' => $context->requestedScopes,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ],
            );

            if ($rememberConsent === true) {
                $rememberedConsent = $this->rememberedConsentService->storeApprovedConsent(
                    user: $user,
                    client: $client,
                    scopeCodes: $context->requestedScopes,
                    redirectUri: $context->redirectUri,
                );

                $this->auditLogService->logSuccess(
                    logName: AuditLogService::LOG_OAUTH,
                    event: 'oauth.consent.remembered',
                    description: 'OAuth consent remembered.',
                    subject: $rememberedConsent,
                    causer: $user,
                    properties: [
                        'user_id' => $user->id,
                        'client_id' => $client->id,
                        'client_public_id' => $client->client_id,
                        'consent_id' => $rememberedConsent->id,
                        'scope_codes' => $context->requestedScopes,
                        'expires_at' => $rememberedConsent->expires_at?->toIso8601String(),
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                    ],
                );
            }

            return $result;
        });

        $this->consentContextService->invalidateContext($consentToken);

        return $result;
    }

    /**
     * Deny a consent decision using the server-side consent context token.
     *
     * @return ConsentDecisionResult
     */
    public function denyConsent(User $user, string $consentToken): array
    {
        try {
            $context = $this->consentContextService->getContextByToken($consentToken);
        } catch (OAuthConsentContextNotFoundException) {
            throw ValidationException::withMessages([
                'consent_token' => Localization::translate('api.oauth.consent.token_invalid'),
            ]);
        }

        if ($context->userId !== $user->id) {
            $this->consentContextService->invalidateContext($consentToken);

            throw ValidationException::withMessages([
                'consent_token' => Localization::translate('api.oauth.consent.token_user_mismatch'),
            ]);
        }

        $redirectUrl = $this->buildAuthorizationErrorRedirect(
            redirectUri: $context->redirectUri,
            error: 'access_denied',
            description: Localization::translate('api.oauth.access_denied'),
            state: $context->state,
        );

        $this->consentContextService->invalidateContext($consentToken);

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.consent.denied',
            description: 'OAuth consent denied.',
            subject: null,
            causer: $user,
            properties: [
                'client_id' => $context->clientDbId,
                'client_public_id' => $context->clientId,
                'scope_codes' => $context->requestedScopes,
            ],
        );

        return [
            'redirect_url' => $redirectUrl,
        ];
    }

    private function buildAuthorizationErrorRedirect(
        string $redirectUri,
        string $error,
        ?string $description = null,
        ?string $state = null,
    ): string {
        $query = array_filter([
            'error' => $error,
            'error_description' => $description,
            'state' => $state,
        ], static fn ($value) => $value !== null && $value !== '');

        return $redirectUri.(str_contains($redirectUri, '?') ? '&' : '?').http_build_query($query);
    }

    /**
     * @param  array<int, string>  $requestedScopes
     * @return AuthorizationApproval
     */
    private function issueAuthorizationCode(
        SsoClient $client,
        User $user,
        ?TokenPolicy $policy,
        string $redirectUri,
        array $requestedScopes,
        ?string $state,
        ?string $nonce,
        string $codeChallenge,
        string $codeChallengeMethod,
    ): array {
        $plainCode = Str::random(64);
        $sid = \in_array('openid', $requestedScopes, true)
            ? $this->oidcSessionService->issueSidForClientSession($client, $user)
            : null;

        DB::transaction(function () use ($client, $user, $policy, $plainCode, $redirectUri, $requestedScopes, $nonce, $sid, $codeChallenge, $codeChallengeMethod): void {
            AuthorizationCode::query()->create([
                'sso_client_id' => $client->id,
                'user_id' => $user->id,
                'token_policy_id' => $policy?->id,
                'code_hash' => hash('sha256', $plainCode),
                'redirect_uri' => $redirectUri,
                'redirect_uri_hash' => hash('sha256', $redirectUri),
                'nonce' => $nonce !== null && $nonce !== '' ? $nonce : null,
                'oidc_sid' => $sid,
                'code_challenge' => $codeChallenge !== '' ? $codeChallenge : null,
                'code_challenge_method' => $codeChallengeMethod !== '' ? $codeChallengeMethod : null,
                'scopes' => $requestedScopes,
                'expires_at' => now()->addMinutes(10),
            ]);

            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_OAUTH,
                event: 'oauth.authorization_code.issued',
                description: 'OAuth authorization code issued.',
                subject: $client,
                causer: $user,
                properties: [
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'scope_codes' => $requestedScopes,
                    'redirect_uri' => $redirectUri,
                    'has_nonce' => $nonce !== null && $nonce !== '',
                    'scope_contains_openid' => \in_array('openid', $requestedScopes, true),
                ],
            );

            if (in_array('openid', $requestedScopes, true)) {
                $this->auditLogService->logSuccess(
                    logName: AuditLogService::LOG_OAUTH,
                    event: 'oauth.nonce.bound_to_authorization_code',
                    description: 'OAuth nonce bound to authorization code.',
                    subject: $client,
                    causer: $user,
                    properties: [
                        'client_id' => $client->id,
                        'client_public_id' => $client->client_id,
                        'target_user_id' => $user->id,
                        'has_nonce' => $nonce !== null && $nonce !== '',
                        'scope_contains_openid' => true,
                    ],
                );
            }
        });

        if ($sid !== null) {
            $this->frontChannelLogoutService->registerParticipatingClient($client, $sid, $user);
        }

        $query = array_filter([
            'code' => $plainCode,
            'state' => $state,
        ], static fn ($value) => $value !== null && $value !== '');

        return [
            'redirect_url' => $redirectUri.(str_contains($redirectUri, '?') ? '&' : '?').http_build_query($query),
            'code' => $plainCode,
            'client' => $client,
            'scopes' => $requestedScopes,
        ];
    }

    /**
     * Resolve the requested scopes and reject any scope that is not assigned to the client.
     *
     * @return array<int, string>
     */
    private function resolveActiveClientOrFail(User $user, array $payload): SsoClient
    {
        /** @var SsoClient|null $client */
        $client = SsoClient::query()
            ->with(['tokenPolicy', 'scopes'])
            ->where('client_id', (string) $payload['client_id'])
            ->where('is_active', true)
            ->first();

        if ($client instanceof SsoClient) {
            return $client;
        }

        $this->logAuthorizationFailure(
            user: $user,
            event: 'oauth.authorization.denied',
            message: Localization::translate('api.oauth.authorization_denied'),
            properties: [
                'client_public_id' => (string) $payload['client_id'],
                'reason' => 'invalid_client',
            ],
        );

        throw ValidationException::withMessages([
            'client_id' => Localization::translate('api.oauth.client_invalid_or_inactive'),
        ]);
    }

    private function assertRedirectUriMatches(User $user, SsoClient $client, string $redirectUri): void
    {
        if ($this->redirectUriMatcher->matches($client, $redirectUri)) {
            return;
        }

        $this->logAuthorizationFailure(
            user: $user,
            event: 'oauth.authorization.denied',
            message: Localization::translate('api.oauth.authorization_denied'),
            client: $client,
            properties: [
                'reason' => 'redirect_uri_mismatch',
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'redirect_uri' => $redirectUri,
            ],
        );

        throw ValidationException::withMessages([
            'redirect_uri' => Localization::translate('api.oauth.redirect_uri_mismatch'),
        ]);
    }

    private function resolveScopes(SsoClient $client, string $scopeString, ?User $user = null): array
    {
        $allowed = $client->normalizedScopeCodes();
        $requested = collect(preg_split('/\s+/', trim($scopeString)) ?: [])
            ->map(static fn (string $scope): string => trim($scope))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($requested === []) {
            return $this->resolveDefaultScopes($client, $user);
        }

        foreach ($requested as $scope) {
            if (! \in_array($scope, $allowed, true)) {
                $this->logAuthorizationFailure(
                    user: $user,
                    event: 'oauth.authorization.denied',
                    message: Localization::translate('api.oauth.authorization_denied'),
                    client: $client,
                    properties: [
                        'reason' => 'scope_not_allowed',
                        'client_id' => $client->id,
                        'client_public_id' => $client->client_id,
                        'scope_codes' => [$scope],
                    ],
                );

                throw ValidationException::withMessages([
                    'scope' => Localization::translate('api.oauth.scope_not_allowed', ['scope' => $scope]),
                ]);
            }
        }

        return $requested;
    }

    /**
     * @return array<int, string>
     */
    private function resolveDefaultScopes(SsoClient $client, ?User $user): array
    {
        $defaultScopes = $client->defaultScopeCodes();

        if ($defaultScopes === []) {
            $this->logAuthorizationFailure(
                user: $user,
                event: 'oauth.authorization.denied',
                message: Localization::translate('api.oauth.authorization_denied'),
                client: $client,
                properties: [
                    'reason' => 'scope_default_missing',
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                ],
            );

            throw ValidationException::withMessages([
                'scope' => Localization::translate('api.oauth.scope_default_missing'),
            ]);
        }

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.scope.default_applied',
            description: 'OAuth default scopes applied.',
            subject: $client,
            causer: $user,
            properties: [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'default_scopes' => $defaultScopes,
            ],
        );

        return $defaultScopes;
    }

    private function assertPkceRequirements(
        User $user,
        SsoClient $client,
        ?TokenPolicy $policy,
        string $codeChallenge,
        string $codeChallengeMethod,
    ): void {
        if ($policy?->pkce_required && $codeChallenge === '') {
            $this->rejectPkceAuthorization($user, $client, 'pkce_required', 'code_challenge', Localization::translate('api.oauth.pkce_required'));
        }

        if ($codeChallenge === '' && $codeChallengeMethod !== '') {
            $this->rejectPkceAuthorization($user, $client, 'pkce_challenge_missing', 'code_challenge', Localization::translate('api.oauth.pkce_required'));
        }

        if ($codeChallenge !== '' && $codeChallengeMethod !== 'S256') {
            $this->rejectPkceAuthorization($user, $client, 'pkce_method_not_s256', 'code_challenge_method', Localization::translate('api.oauth.code_challenge_method_s256'));
        }
    }

    private function rejectPkceAuthorization(
        User $user,
        SsoClient $client,
        string $reason,
        string $field,
        string $message,
    ): never {
        $this->logAuthorizationFailure(
            user: $user,
            event: 'oauth.authorization.denied',
            message: Localization::translate('api.oauth.authorization_denied'),
            client: $client,
            properties: [
                'reason' => $reason,
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
            ],
        );

        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }

    /**
     * Resolve the active token policy attached to the client or the current default policy.
     */
    private function resolvePolicy(SsoClient $client): ?TokenPolicy
    {
        if ($client->relationLoaded('tokenPolicy') && $client->tokenPolicy !== null) {
            return $client->tokenPolicy;
        }

        return TokenPolicy::query()
            ->when($client->token_policy_id !== null, fn ($query) => $query->whereKey($client->token_policy_id))
            ->when($client->token_policy_id === null, fn ($query) => $query->where('is_default', true))
            ->where('is_active', true)
            ->first();
    }

    /**
     * @param  array<int, string>  $requestedScopes
     * @return array<int, array{code: string, name: string, description: string|null}>
     */
    private function buildConsentScopeView(SsoClient $client, array $requestedScopes): array
    {
        $scopes = $client->relationLoaded('scopes')
            ? $client->getRelation('scopes')
            : $client->scopes()->get();

        return collect($requestedScopes)
            ->map(function (string $scopeCode) use ($scopes): array {
                /** @var Scope|null $scope */
                $scope = $scopes->firstWhere('code', $scopeCode);

                return [
                    'code' => $scopeCode,
                    'name' => $this->resolveScopeDisplayName($scopeCode, $scope),
                    'description' => $this->normalizeNullableString($scope?->description),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     name: string,
     *     description: string,
     *     originHost: string|null,
     *     returnPath: string|null,
     *     trustLabel: string,
     *     trustDescription: string
     * }
     */
    private function buildConsentClientView(SsoClient $client, string $redirectUri): array
    {
        $parsedRedirectUri = parse_url($redirectUri);
        $originHost = \is_array($parsedRedirectUri) ? ($parsedRedirectUri['host'] ?? null) : null;
        $returnPath = \is_array($parsedRedirectUri) ? ($parsedRedirectUri['path'] ?? null) : null;

        return [
            'name' => $this->resolveClientDisplayName($client),
            'description' => $this->resolveClientDescription($client),
            'originHost' => \is_string($originHost) && trim($originHost) !== '' ? strtolower(trim($originHost)) : null,
            'returnPath' => \is_string($returnPath) && trim($returnPath) !== '' ? trim($returnPath) : null,
            'trustLabel' => $this->resolveConsentTrustLabel($client),
            'trustDescription' => $this->resolveConsentTrustDescription($client),
        ];
    }

    private function resolveClientDisplayName(SsoClient $client): string
    {
        $name = $this->normalizeNullableString($client->name);

        return $name ?? $client->client_id;
    }

    private function resolveConsentTrustLabel(SsoClient $client): string
    {
        return match ($client->trust_tier) {
            SsoClient::TRUST_TIER_FIRST_PARTY_TRUSTED => 'Trusted first-party application',
            SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED => 'First-party application',
            SsoClient::TRUST_TIER_MACHINE_TO_MACHINE => 'System-managed client',
            default => 'Third-party application',
        };
    }

    private function resolveConsentTrustDescription(SsoClient $client): string
    {
        return match ($client->trust_tier) {
            SsoClient::TRUST_TIER_FIRST_PARTY_TRUSTED => 'Managed inside this identity environment with a trusted first-party policy.',
            SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED => 'Managed inside this identity environment, but it still requires your explicit approval.',
            SsoClient::TRUST_TIER_MACHINE_TO_MACHINE => 'Registered for system integrations. Interactive access stays subject to explicit policy checks.',
            default => 'Registered outside the core first-party trust tier, so review the requested access carefully.',
        };
    }

    private function resolveClientDescription(SsoClient $client): string
    {
        return $this->normalizeNullableString($client->getAttribute('description'))
            ?? 'This application is requesting permission to access your account through the identity provider.';
    }

    private function resolveScopeDisplayName(string $scopeCode, ?Scope $scope): string
    {
        return $this->normalizeNullableString($scope?->name)
            ?? Str::headline(str_replace(['.', '-', '_', ':'], ' ', $scopeCode));
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<int, string>  $requestedScopes
     */
    private function logNonceAccepted(User $user, SsoClient $client, array $requestedScopes, string $nonce): void
    {
        if (! \in_array('openid', $requestedScopes, true)) {
            return;
        }

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.nonce.accepted',
            description: 'OAuth nonce accepted for authorization request.',
            subject: $client,
            causer: $user,
            properties: [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'target_user_id' => $user->id,
                'has_nonce' => $nonce !== '',
                'scope_contains_openid' => true,
            ],
        );
    }

    private function logTrustDecision(User $user, SsoClient $client, OAuthTrustDecisionResult $decision): void
    {
        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: \sprintf('oauth.trust_decision.%s', $decision->decision->value),
            description: 'OAuth trust policy decision evaluated.',
            subject: $client,
            causer: $user,
            properties: [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'target_user_id' => $user->id,
                'trust_tier' => $decision->trustTier,
                'consent_bypass_allowed' => $decision->consentBypassAllowed,
                'decision' => $decision->decision->value,
                'reason' => $decision->reason,
            ],
        );
    }

    /**
     * Remembered consent reuse is separate from trusted-client bypass.
     * It only refines the show_consent branch with conservative exact-match checks.
     *
     * @param  array<int, string>  $requestedScopes
     */
    private function logRememberedConsentDecision(
        User $user,
        SsoClient $client,
        array $requestedScopes,
        OAuthRememberedConsentDecisionResult $decision,
    ): void {
        $event = match ($decision->reason) {
            'remembered_consent_match' => 'oauth.remembered_consent.used',
            'remembered_consent_missing' => 'oauth.remembered_consent.not_used',
            default => 'oauth.remembered_consent.mismatch',
        };

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: $event,
            description: 'OAuth remembered consent decision evaluated.',
            subject: $client,
            causer: $user,
            properties: [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'target_user_id' => $user->id,
                'granted_scope_fingerprint' => $decision->consent?->granted_scope_fingerprint
                    ?? $this->rememberedConsentService->scopeFingerprint($requestedScopes),
                'trust_tier' => $decision->consent?->trust_tier_snapshot,
                'consent_policy_version' => $decision->consent?->consent_policy_version,
                'decision' => $decision->shouldReuse ? 'skip_consent' : 'show_consent',
                'reason' => $decision->reason,
            ],
        );
    }

    /**
     * Record an auditable OAuth authorization failure without exposing sensitive values.
     *
     * @param  array<string, mixed>  $properties
     */
    private function logAuthorizationFailure(
        ?User $user,
        string $event,
        string $message,
        ?SsoClient $client = null,
        array $properties = [],
    ): void {
        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_OAUTH,
            event: $event,
            description: $message,
            subject: $client,
            causer: $user,
            properties: $properties,
        );
    }
}
