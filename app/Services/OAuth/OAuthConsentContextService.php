<?php

namespace App\Services\OAuth;

use App\Data\OAuth\OAuthConsentContextData;
use App\Exceptions\OAuth\OAuthConsentContextNotFoundException;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Support\Localization;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;

/**
 * OAuth consent contextek szerveroldali kezeléséért felelős szolgáltatás.
 *
 * A consent context célja, hogy a felhasználói jóváhagyás idejére
 * biztonságosan megőrizzük az authorization request eredeti állapotát.
 * Így a kliensoldali consent képernyő nem tudja utólag módosítani
 * a redirect URI-t, scope-okat, nonce-t vagy PKCE paramétereket.
 *
 * @phpstan-type AuthorizationPayload array{
 *     response_type: string,
 *     client_id: string,
 *     redirect_uri: string,
 *     scope?: string|null,
 *     state?: string|null,
 *     nonce?: string|null,
 *     code_challenge?: string|null,
 *     code_challenge_method?: string|null
 * }
 */
class OAuthConsentContextService
{
    /**
     * Session kulcs, amely alatt az aktív OAuth consent contextek tárolódnak.
     */
    private const SESSION_KEY = 'oauth.consent_contexts';

    public function __construct(
        private readonly RedirectUriMatcher $redirectUriMatcher,
        private readonly Session $session,
    ) {}

    /**
     * Létrehoz egy új consent contextet egy authorization request alapján.
     *
     * A metódus újraellenőrzi a klienst, redirect URI-t, scope-okat és PKCE
     * követelményeket, mielőtt a jóváhagyási állapotot sessionbe mentené.
     * Ez védi a consent folyamatot a manipulált vagy elavult request adatoktól.
     *
     * @param  AuthorizationPayload  $payload
     */
    public function createContext(User $user, array $payload): OAuthConsentContextData
    {
        /** @var SsoClient|null $client */
        $client = SsoClient::query()
            ->with(['redirectUris', 'scopes', 'tokenPolicy'])
            ->where('client_id', (string) $payload['client_id'])
            ->where('is_active', true)
            ->first();

        if (! $client instanceof SsoClient) {
            throw ValidationException::withMessages([
                'client_id' => 'The provided client is invalid or inactive.',
            ]);
        }

        $redirectUri = (string) $payload['redirect_uri'];

        if (! $this->redirectUriMatcher->matches($client, $redirectUri)) {
            throw ValidationException::withMessages([
                'redirect_uri' => 'The redirect URI does not match the registered client redirect URIs.',
            ]);
        }

        $requestedScopes = $this->resolveScopes($client, (string) ($payload['scope'] ?? ''));
        $this->assertPkceRequirements($client, $payload);

        $createdAt = CarbonImmutable::now();

        return $this->storeContext(
            user: $user,
            client: $client,
            payload: $payload,
            requestedScopes: $requestedScopes,
            createdAt: $createdAt,
        );
    }

    /**
     * Eltárolja a consent döntéshez szükséges authorization állapotot.
     *
     * A context rövid életű, tokennel azonosított szerveroldali snapshot.
     * A consent képernyő később csak erre a tokenre hivatkozik, nem küldi
     * újra szabadon módosítható OAuth paraméterként a teljes requestet.
     *
     * @param  AuthorizationPayload  $payload
     * @param  array<int, string>  $requestedScopes
     */
    public function storeContext(
        User $user,
        SsoClient $client,
        array $payload,
        array $requestedScopes,
        ?CarbonImmutable $createdAt = null,
    ): OAuthConsentContextData {
        $createdAt ??= CarbonImmutable::now();
        $expiresAt = $createdAt->addMinutes($this->ttlMinutes());

        $context = new OAuthConsentContextData(
            consentToken: bin2hex(random_bytes(32)),
            clientId: $client->client_id,
            clientDbId: $client->id,
            clientDisplayName: $client->name,
            clientDescription: $this->normalizeNullableString($client->getAttribute('description')),
            redirectUri: (string) $payload['redirect_uri'],
            requestedScopes: $requestedScopes,
            state: $this->normalizeNullableString($payload['state'] ?? null),
            nonce: $this->normalizeNullableString($payload['nonce'] ?? null),
            responseType: trim((string) $payload['response_type']),
            codeChallenge: $this->normalizeNullableString($payload['code_challenge'] ?? null),
            codeChallengeMethod: $this->normalizeNullableString($payload['code_challenge_method'] ?? null),
            userId: $user->id,
            createdAt: $createdAt->toIso8601String(),
            expiresAt: $expiresAt->toIso8601String(),
        );

        $contexts = $this->allContexts();
        $contexts[$context->consentToken] = $context->toSessionPayload();

        $this->session->put(self::SESSION_KEY, $contexts);

        return $context;
    }

    /**
     * Consent token alapján visszaadja a még érvényes szerveroldali contextet.
     *
     * Lejárt vagy hiányzó context esetén ugyanúgy hibát dobunk, hogy a hívó
     * réteg ne különböztesse meg feleslegesen az invalid és expired állapotokat.
     */
    public function getContextByToken(string $token): OAuthConsentContextData
    {
        $payload = $this->allContexts()[trim($token)] ?? null;

        if (! \is_array($payload)) {
            throw OAuthConsentContextNotFoundException::missingOrExpired();
        }

        $context = OAuthConsentContextData::fromSessionPayload($payload);

        if ($context->isExpired()) {
            $this->invalidateContext($token);

            throw OAuthConsentContextNotFoundException::missingOrExpired();
        }

        return $context;
    }

    /**
     * Érvényteleníti a consent contextet.
     *
     * Ezt jóváhagyás, elutasítás, lejárat vagy felhasználói eltérés esetén
     * használjuk, hogy ugyanaz a consent token ne legyen újra felhasználható.
     */
    public function invalidateContext(string $token): void
    {
        $normalizedToken = trim($token);
        $contexts = $this->allContexts();

        if (! \array_key_exists($normalizedToken, $contexts)) {
            return;
        }

        unset($contexts[$normalizedToken]);
        $this->session->put(self::SESSION_KEY, $contexts);
    }

    /**
     * Eldönti, hogy egy consent token jelenleg még használható-e.
     *
     * Kényelmi ellenőrző metódus olyan UI vagy flow pontokra, ahol nem a
     * context tartalma kell, csak annak eldöntése, hogy folytatható-e a consent.
     */
    public function hasValidContext(string $token): bool
    {
        try {
            $this->getContextByToken($token);

            return true;
        } catch (OAuthConsentContextNotFoundException) {
            return false;
        }
    }

    /**
     * Visszaadja a sessionben tárolt összes consent contextet.
     *
     * Ha a session értéke sérült vagy nem tömb, üres listával térünk vissza,
     * hogy a hibás session állapot ne okozzon authorization folyamat közbeni hibát.
     *
     * @return array<string, array<string, mixed>>
     */
    private function allContexts(): array
    {
        $contexts = $this->session->get(self::SESSION_KEY, []);

        return \is_array($contexts) ? $contexts : [];
    }

    /**
     * Feloldja az authorization requestben kért scope-okat.
     *
     * Explicit scope kérés esetén minden scope-nak szerepelnie kell a klienshez
     * rendelt engedélyezett scope-listában. Scope nélküli kérésnél kizárólag
     * a kliens default scope-jai alkalmazhatók.
     *
     * @return array<int, string>
     */
    private function resolveScopes(SsoClient $client, string $scopeString): array
    {
        $allowed = $client->normalizedScopeCodes();
        $requested = collect(preg_split('/\s+/', trim($scopeString)) ?: [])
            ->map(static fn (string $scope): string => trim($scope))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($requested === []) {
            $defaultScopes = $client->defaultScopeCodes();

            if ($defaultScopes === []) {
                throw ValidationException::withMessages([
                    'scope' => Localization::translate('api.oauth.scope_default_missing'),
                ]);
            }

            return $defaultScopes;
        }

        foreach ($requested as $scope) {
            if (! \in_array($scope, $allowed, true)) {
                throw ValidationException::withMessages([
                    'scope' => Localization::translate('api.oauth.scope_not_allowed', ['scope' => $scope]),
                ]);
            }
        }

        return $requested;
    }

    /**
     * Ellenőrzi a consent contexthez kapcsolódó PKCE követelményeket.
     *
     * Ha a kliens token policy-ja PKCE-t ír elő, nem engedünk consent contextet
     * létrehozni code challenge nélkül. Challenge megadása esetén csak S256
     * elfogadott, hogy a jóváhagyott request később biztonságosan cserélhető
     * legyen tokenre.
     *
     * @param  AuthorizationPayload  $payload
     */
    private function assertPkceRequirements(SsoClient $client, array $payload): void
    {
        $policy = $this->resolvePolicy($client);
        $codeChallenge = trim((string) ($payload['code_challenge'] ?? ''));
        $codeChallengeMethod = trim((string) ($payload['code_challenge_method'] ?? ''));

        if ($policy?->pkce_required && $codeChallenge === '') {
            throw ValidationException::withMessages([
                'code_challenge' => \sprintf('PKCE is required for this client.'),
            ]);
        }

        if ($codeChallenge !== '' && $codeChallengeMethod !== 'S256') {
            throw ValidationException::withMessages([
                'code_challenge_method' => \sprintf('The code challenge method must be S256.'),
            ]);
        }
    }

    /**
     * Meghatározza a kliensre érvényes aktív token policy-t.
     *
     * Konkrét kliens policy esetén azt használjuk, egyébként az aktív default
     * policy-t keressük. Ez biztosítja, hogy a consent context ugyanazokra
     * a tokenkiadási szabályokra épüljön, mint az authorization flow.
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
     * Visszaadja a consent context élettartamát percekben.
     *
     * Az alsó korlát megakadályozza, hogy hibás konfiguráció miatt azonnal
     * lejáró vagy használhatatlan consent context jöjjön létre.
     */
    private function ttlMinutes(): int
    {
        return max(1, (int) config('services.oauth.consent_context_ttl_minutes', 5));
    }

    /**
     * Egységesen normalizálja az opcionális szöveges mezőket.
     *
     * Az üres stringeket nullként kezeljük, hogy a session payload,
     * összehasonlítások és későbbi OAuth döntések konzisztensen működjenek.
     */
    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }
}