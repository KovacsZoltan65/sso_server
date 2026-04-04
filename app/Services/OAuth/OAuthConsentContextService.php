<?php

namespace App\Services\OAuth;

use App\Data\OAuth\OAuthConsentContextData;
use App\Exceptions\OAuth\OAuthConsentContextNotFoundException;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type AuthorizationPayload array{
 *     response_type: string,
 *     client_id: string,
 *     redirect_uri: string,
 *     scope?: string|null,
 *     state?: string|null,
 *     code_challenge?: string|null,
 *     code_challenge_method?: string|null
 * }
 */
class OAuthConsentContextService
{
    private const SESSION_KEY = 'oauth.consent_contexts';

    public function __construct(
        private readonly RedirectUriMatcher $redirectUriMatcher,
        private readonly Session $session,
    ) {
    }

    /**
     * @param AuthorizationPayload $payload
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

        $redirectUri = trim((string) $payload['redirect_uri']);

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
     * @param AuthorizationPayload $payload
     * @param array<int, string> $requestedScopes
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
            redirectUri: trim((string) $payload['redirect_uri']),
            requestedScopes: $requestedScopes,
            state: $this->normalizeNullableString($payload['state'] ?? null),
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

    public function getContextByToken(string $token): OAuthConsentContextData
    {
        $payload = $this->allContexts()[trim($token)] ?? null;

        if (! is_array($payload)) {
            throw OAuthConsentContextNotFoundException::missingOrExpired();
        }

        $context = OAuthConsentContextData::fromSessionPayload($payload);

        if ($context->isExpired()) {
            $this->invalidateContext($token);

            throw OAuthConsentContextNotFoundException::missingOrExpired();
        }

        return $context;
    }

    public function invalidateContext(string $token): void
    {
        $normalizedToken = trim($token);
        $contexts = $this->allContexts();

        if (! array_key_exists($normalizedToken, $contexts)) {
            return;
        }

        unset($contexts[$normalizedToken]);
        $this->session->put(self::SESSION_KEY, $contexts);
    }

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
     * @return array<string, array<string, mixed>>
     */
    private function allContexts(): array
    {
        $contexts = $this->session->get(self::SESSION_KEY, []);

        return is_array($contexts) ? $contexts : [];
    }

    /**
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
            return $allowed;
        }

        foreach ($requested as $scope) {
            if (! in_array($scope, $allowed, true)) {
                throw ValidationException::withMessages([
                    'scope' => sprintf('The requested scope [%s] is not allowed for this client.', $scope),
                ]);
            }
        }

        return $requested;
    }

    /**
     * @param AuthorizationPayload $payload
     */
    private function assertPkceRequirements(SsoClient $client, array $payload): void
    {
        $policy = $this->resolvePolicy($client);
        $codeChallenge = trim((string) ($payload['code_challenge'] ?? ''));
        $codeChallengeMethod = trim((string) ($payload['code_challenge_method'] ?? ''));

        if ($policy?->pkce_required && $codeChallenge === '') {
            throw ValidationException::withMessages([
                'code_challenge' => 'PKCE is required for this client.',
            ]);
        }

        if ($codeChallenge !== '' && $codeChallengeMethod !== 'S256') {
            throw ValidationException::withMessages([
                'code_challenge_method' => 'The code challenge method must be S256.',
            ]);
        }
    }

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

    private function ttlMinutes(): int
    {
        return max(1, (int) config('services.oauth.consent_context_ttl_minutes', 5));
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }
}
