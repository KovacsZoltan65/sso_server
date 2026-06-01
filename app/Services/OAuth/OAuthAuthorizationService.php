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
 * OAuth authorization flow üzleti logikáját vezérlő szolgáltatás.
 *
 * Ez az osztály felel az authorize endpoint fő döntési pontjaiért:
 * - kliens és redirect URI ellenőrzés
 * - scope feloldás és default scope kezelés
 * - PKCE és OIDC nonce validáció
 * - felhasználó-kliens hozzáférési döntés
 * - trust policy és remembered consent kiértékelés
 * - authorization code kiadás
 * - consent döntések auditálása
 *
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
     * Előkészíti az OAuth consent döntést egy validált authorization request alapján.
     *
     * A metódus célja, hogy eldöntse:
     * - azonnal kiadható-e authorization code
     * - el kell-e utasítani a kérést
     * - vagy felhasználói consent képernyőt kell megjeleníteni
     *
     * A döntés során figyelembe veszi a kliens állapotát, redirect URI-t,
     * scope-okat, PKCE szabályokat, felhasználói hozzáférést, trust policy-t
     * és korábban megjegyzett consent döntéseket.
     *
     * @param  AuthorizationPayload  $payload
     * @return ConsentPreparationResult|AuthorizationRedirectResult
     */
    public function prepareConsent(User $user, array $payload): array
    {
        $client = $this->resolveActiveClientOrFail($user, $payload);
        $redirectUri = (string) $payload['redirect_uri'];

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
     * Jóváhagy egy authorization requestet és kiadja az authorization code-ot.
     *
     * Ez a metódus közvetlen jóváhagyási útvonalra használható, ahol a consent
     * döntés már megtörtént vagy nem szükséges külön consent context alapján.
     *
     * @param  AuthorizationPayload  $payload
     * @return AuthorizationApproval
     */
    public function approve(User $user, array $payload): array
    {
        $client = $this->resolveActiveClientOrFail($user, $payload);
        $redirectUri = (string) $payload['redirect_uri'];
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
     * Consent token alapján jóváhagyja a felhasználó consent döntését.
     *
     * A consent context szerveroldali tokennel védi az authorization requestet,
     * így a kliensoldal nem módosíthatja utólag a jóváhagyott scope-okat,
     * redirect URI-t vagy PKCE/OIDC paramétereket.
     *
     * A remember consent opció csak sikeres jóváhagyás után tárolódik,
     * és audit eseményként is rögzül.
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
     * Consent token alapján elutasítja az authorization requestet.
     *
     * Az elutasítás OAuth-kompatibilis redirecttel tér vissza a klienshez,
     * miközben a szerveroldali consent context érvénytelenítésre kerül,
     * hogy ugyanaz a döntési token ne legyen újra felhasználható.
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

    /**
     * OAuth hibaválaszt épít visszairányításhoz.
     *
     * A kliens az authorize flow hibáit redirecten keresztül kapja vissza,
     * ezért a hiba, opcionális leírás és state paraméter a redirect URI
     * query részébe kerül.
     */
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
     * Authorization code-ot ad ki a jóváhagyott OAuth kéréshez.
     *
     * A nyers code csak a redirect válaszban jelenik meg, az adatbázisban
     * kizárólag hash formában tárolódik. OIDC kérés esetén sid is kapcsolódik
     * a kliens munkamenethez, hogy front-channel logout során követhető legyen
     * a résztvevő kliens.
     *
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
     * Aktív OAuth klienst keres a publikus client_id alapján.
     *
     * Inaktív vagy ismeretlen klienssel nem indulhat authorization flow.
     * A hibát auditáljuk, de érzékeny kliensadatot nem szivárogtatunk vissza.
     *
     * @param  AuthorizationPayload  $payload
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

    /**
     * Ellenőrzi, hogy a kért redirect URI pontosan megfelel-e a kliens konfigurációjának.
     *
     * Ez az authorize flow egyik legfontosabb védelmi pontja, mert hibás vagy
     * manipulált redirect URI authorization code kiszivárgáshoz vezethetne.
     */
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

    /**
     * Feloldja és ellenőrzi az authorization requestben kért scope-okat.
     *
     * Explicit scope kérés esetén minden scope-nak szerepelnie kell a klienshez
     * rendelt engedélyezett scope-listában. Üres scope kérésnél kizárólag a
     * kliens default scope-jai alkalmazhatók.
     *
     * @return array<int, string>
     */
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
     * Alkalmazza a kliens default scope-jait explicit scope kérés hiányában.
     *
     * Default scope nélkül nem adunk ki authorization code-ot, mert az üres
     * scope implicit és nehezen auditálható jogosultsági döntést eredményezne.
     *
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

    /**
     * Ellenőrzi a PKCE követelményeket az authorization requestben.
     *
     * Ha a token policy PKCE-t ír elő, code challenge nélkül nem indulhat
     * authorization flow. Challenge megadása esetén csak S256 elfogadott,
     * hogy a plain vagy gyengébb PKCE használat ne gyengítse a flow-t.
     */
    private function assertPkceRequirements(
        User $user,
        SsoClient $client,
        ?TokenPolicy $policy,
        string $codeChallenge,
        string $codeChallengeMethod,
    ): void {
        if (($client->isPublic() || $policy?->pkce_required) && $codeChallenge === '') {
            $this->rejectPkceAuthorization($user, $client, 'pkce_required', 'code_challenge', Localization::translate('api.oauth.pkce_required'));
        }

        if ($codeChallenge === '' && $codeChallengeMethod !== '') {
            $this->rejectPkceAuthorization($user, $client, 'pkce_challenge_missing', 'code_challenge', Localization::translate('api.oauth.pkce_required'));
        }

        if ($codeChallenge !== '' && $codeChallengeMethod !== 'S256') {
            $this->rejectPkceAuthorization($user, $client, 'pkce_method_not_s256', 'code_challenge_method', Localization::translate('api.oauth.code_challenge_method_s256'));
        }
    }

    /**
     * Elutasítja a PKCE szempontból nem biztonságos authorization requestet.
     *
     * A visszautasítás auditálva történik, hogy később látható legyen,
     * klienshiba vagy lehetséges visszaélési kísérlet okozta-e a hibát.
     */
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
     * Meghatározza a kliensre érvényes aktív token policy-t.
     *
     * Ha a klienshez konkrét policy tartozik, azt használjuk. Ennek hiányában
     * az aktuális aktív default policy érvényesül, így központi szabályokkal
     * kezelhető a tokenek élettartama és PKCE követelménye.
     */
    private function resolvePolicy(SsoClient $client): ?TokenPolicy
    {
        if ($client->relationLoaded('tokenPolicy') && $client->tokenPolicy !== null) {
            return $this->assertPolicyAllowedForClientType($client, $client->tokenPolicy);
        }

        $policy = TokenPolicy::query()
            ->when($client->token_policy_id !== null, fn ($query) => $query->whereKey($client->token_policy_id))
            ->when($client->token_policy_id === null, fn ($query) => $query->where('is_default', true))
            ->where('is_active', true)
            ->first();

        return $policy instanceof TokenPolicy
            ? $this->assertPolicyAllowedForClientType($client, $policy)
            : null;
    }

    private function assertPolicyAllowedForClientType(SsoClient $client, TokenPolicy $policy): TokenPolicy
    {
        if ($client->isPublic() && ! $policy->pkce_required) {
            $this->logAuthorizationFailure(
                user: null,
                event: 'oauth.authorization.denied',
                message: Localization::translate('api.oauth.authorization_denied'),
                client: $client,
                properties: [
                    'reason' => 'public_client_policy_requires_pkce',
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'policy_id' => $policy->id,
                ],
            );

            throw ValidationException::withMessages([
                'client_id' => 'Public clients must use a token policy where PKCE is required.',
            ]);
        }

        return $policy;
    }

    /**
     * Felépíti a consent képernyőn megjelenő scope listát.
     *
     * A felhasználó nem technikai scope kódokat, hanem érthető neveket
     * és leírásokat kap, hogy tudatos consent döntést hozhasson.
     *
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
     * Felépíti a consent képernyő kliensinformációs blokkját.
     *
     * A cél, hogy a felhasználó lássa, melyik alkalmazás kér hozzáférést,
     * milyen visszatérési célra irányít, és milyen trust besorolással rendelkezik.
     *
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

    /**
     * Meghatározza a kliens consent képernyőn megjelenő nevét.
     *
     * Ha nincs emberbarát név megadva, a publikus client_id marad fallback,
     * hogy a felhasználó akkor is azonosítani tudja a kérelmező alkalmazást.
     */
    private function resolveClientDisplayName(SsoClient $client): string
    {
        $name = $this->normalizeNullableString($client->name);

        return $name ?? $client->client_id;
    }

    /**
     * Emberbarát trust címkét ad a kliens bizalmi besorolásához.
     *
     * Ez segíti a consent képernyőn a kockázatérzet és a döntési kontextus
     * megértését anélkül, hogy belső enum értékeket jelenítenénk meg.
     */
    private function resolveConsentTrustLabel(SsoClient $client): string
    {
        return match ($client->trust_tier) {
            SsoClient::TRUST_TIER_FIRST_PARTY_TRUSTED => 'Trusted first-party application',
            SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED => 'First-party application',
            SsoClient::TRUST_TIER_MACHINE_TO_MACHINE => 'System-managed client',
            default => 'Third-party application',
        };
    }

    /**
     * Rövid magyarázatot ad a kliens trust besorolásához.
     *
     * A felhasználó így nem csak egy címkét lát, hanem azt is,
     * hogy a besorolás milyen consent és biztonsági következménnyel jár.
     */
    private function resolveConsentTrustDescription(SsoClient $client): string
    {
        return match ($client->trust_tier) {
            SsoClient::TRUST_TIER_FIRST_PARTY_TRUSTED => 'Managed inside this identity environment with a trusted first-party policy.',
            SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED => 'Managed inside this identity environment, but it still requires your explicit approval.',
            SsoClient::TRUST_TIER_MACHINE_TO_MACHINE => 'Registered for system integrations. Interactive access stays subject to explicit policy checks.',
            default => 'Registered outside the core first-party trust tier, so review the requested access carefully.',
        };
    }

    /**
     * Meghatározza a consent képernyőn megjelenő kliensleírást.
     *
     * Leírás hiányában biztonságos, általános szöveget adunk vissza,
     * hogy a consent felület ne maradjon üres vagy félreérthető.
     */
    private function resolveClientDescription(SsoClient $client): string
    {
        return $this->normalizeNullableString($client->getAttribute('description'))
            ?? 'This application is requesting permission to access your account through the identity provider.';
    }

    /**
     * Emberbarát scope nevet állít elő a consent képernyőhöz.
     *
     * Ha a scope törzsadatban nincs külön név, a technikai scope kódból
     * olvasható fallback címke készül.
     */
    private function resolveScopeDisplayName(string $scopeCode, ?Scope $scope): string
    {
        return $this->normalizeNullableString($scope?->name)
            ?? Str::headline(str_replace(['.', '-', '_', ':'], ' ', $scopeCode));
    }

    /**
     * Egységesen normalizálja az opcionális szöveges mezőket.
     *
     * Az üres stringeket nullként kezeljük, hogy a megjelenítési és fallback
     * logika ne különböztesse meg az üres és hiányzó értékeket.
     */
    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Auditálja, hogy OIDC authorization request esetén nonce érkezett.
     *
     * A nonce az ID token replay protection fontos eleme, ezért az openid
     * scope-hoz kapcsolódó nonce állapot külön audit eseményként követhető.
     *
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

    /**
     * Auditálja a trust policy alapján meghozott authorization döntést.
     *
     * A trust döntés külön auditálása megmutatja, hogy miért lett consent
     * kihagyva, megjelenítve vagy miért lett a kérés elutasítva.
     */
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
     * Auditálja a megjegyzett consent újrahasználhatósági döntését.
     *
     * A remembered consent nem azonos a trusted-client bypass-szal:
     * csak akkor használható, ha a korábbi döntés konzervatív, pontos
     * egyezési feltételei teljesülnek.
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
     * Auditálható OAuth authorization hibát rögzít érzékeny értékek nélkül.
     *
     * A központi helper egységesíti a sikertelen authorization eseményeket,
     * így később könnyebb incidenseket, klienshibákat vagy konfigurációs
     * problémákat visszakeresni.
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
