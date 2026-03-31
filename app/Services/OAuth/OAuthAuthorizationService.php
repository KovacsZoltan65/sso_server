<?php

namespace App\Services\OAuth;

use App\Models\AuthorizationCode;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Services\Audit\AuditLogService;
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
 *     code_challenge?: string|null,
 *     code_challenge_method?: string|null
 * }
 * @phpstan-type AuthorizationApproval array{
 *     redirect_url: string,
 *     code: string,
 *     client: SsoClient,
 *     scopes: array<int, string>
 * }
 */
class OAuthAuthorizationService
{
    public function __construct(
        private readonly RedirectUriMatcher $redirectUriMatcher,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * Approve an authorization request and issue a redirectable authorization code.
     *
     * @param AuthorizationPayload $payload
     * @return AuthorizationApproval
     */
    public function approve(User $user, array $payload): array
    {
        /** @var SsoClient|null $client */
        $client = SsoClient::query()
            ->with(['tokenPolicy', 'scopes'])
            ->where('client_id', (string) $payload['client_id'])
            ->where('is_active', true)
            ->first();

        if (! $client instanceof SsoClient) {
            $this->logAuthorizationFailure(
                user: $user,
                event: 'oauth.authorization.denied',
                message: 'OAuth authorization denied.',
                properties: [
                    'client_public_id' => (string) $payload['client_id'],
                    'reason' => 'invalid_client',
                ],
            );

            throw ValidationException::withMessages([
                'client_id' => 'The provided client is invalid or inactive.',
            ]);
        }

        $redirectUri = trim((string) $payload['redirect_uri']);
        if (! $this->redirectUriMatcher->matches($client, $redirectUri)) {
            $this->logAuthorizationFailure(
                user: $user,
                event: 'oauth.authorization.denied',
                message: 'OAuth authorization denied.',
                client: $client,
                properties: [
                    'reason' => 'redirect_uri_mismatch',
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'redirect_uri' => $redirectUri,
                ],
            );

            throw ValidationException::withMessages([
                'redirect_uri' => 'The redirect URI does not match the registered client redirect URIs.',
            ]);
        }

        $requestedScopes = $this->resolveScopes($client, (string) ($payload['scope'] ?? ''));
        $policy = $this->resolvePolicy($client);

        $codeChallenge = trim((string) ($payload['code_challenge'] ?? ''));
        $codeChallengeMethod = trim((string) ($payload['code_challenge_method'] ?? ''));

        if ($policy?->pkce_required && $codeChallenge === '') {
            $this->logAuthorizationFailure(
                user: $user,
                event: 'oauth.authorization.denied',
                message: 'OAuth authorization denied.',
                client: $client,
                properties: [
                    'reason' => 'pkce_required',
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                ],
            );

            throw ValidationException::withMessages([
                'code_challenge' => 'PKCE is required for this client.',
            ]);
        }

        $plainCode = Str::random(64);

        DB::transaction(function () use ($client, $user, $policy, $plainCode, $redirectUri, $requestedScopes, $codeChallenge, $codeChallengeMethod): void {
            AuthorizationCode::query()->create([
                'sso_client_id' => $client->id,
                'user_id' => $user->id,
                'token_policy_id' => $policy?->id,
                'code_hash' => hash('sha256', $plainCode),
                'redirect_uri' => $redirectUri,
                'redirect_uri_hash' => hash('sha256', $redirectUri),
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
                ],
            );
        });

        $query = array_filter([
            'code' => $plainCode,
            'state' => Arr::get($payload, 'state'),
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
                $this->logAuthorizationFailure(
                    user: auth()->user(),
                    event: 'oauth.authorization.denied',
                    message: 'OAuth authorization denied.',
                    client: $client,
                    properties: [
                        'reason' => 'scope_not_allowed',
                        'client_id' => $client->id,
                        'client_public_id' => $client->client_id,
                        'scope_codes' => [$scope],
                    ],
                );

                throw ValidationException::withMessages([
                    'scope' => sprintf('The requested scope [%s] is not allowed for this client.', $scope),
                ]);
            }
        }

        return $requested;
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
     * Record an auditable OAuth authorization failure without exposing sensitive values.
     *
     * @param array<string, mixed> $properties
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
