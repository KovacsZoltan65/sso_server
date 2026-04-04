<?php

namespace App\Services;

use App\Data\ClientSummaryData;
use App\Models\ClientSecret;
use App\Models\SsoClient;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Support\ClientOptions;
use App\Support\Permissions\ClientPermissions;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type AdminClientFilters array{
 *     global?: string|null,
 *     name?: string|null,
 *     status?: string|null
 * }
 * @phpstan-type ClientWritePayload array{
 *     name: string,
 *     is_active: bool,
 *     token_policy_id?: int|null,
 *     trust_tier: string,
 *     is_first_party: bool,
 *     consent_bypass_allowed: bool,
 *     redirect_uris?: array<int, mixed>,
 *     scopes?: array<int, mixed>
 * }
 * @phpstan-type ClientSecretPayload array{
 *     name?: string|null
 * }
 * @phpstan-type AdminClientRow array{
 *     id: int,
 *     name: string,
 *     clientId: string,
 *     redirectUris: array<int, string>,
 *     redirectUriCount: int,
 *     isActive: bool,
 *     scopes: array<int, string>,
 *     scopesCount: int,
 *     tokenPolicyId: int|null,
 *     trustTier: string,
 *     isFirstParty: bool,
 *     consentBypassAllowed: bool,
 *     createdAt: string,
 *     canDelete: bool
 * }
 * @phpstan-type ClientSecretView array{
 *     id: int,
 *     name: string,
 *     lastFour: string,
 *     isActive: bool,
 *     isRevoked: bool,
 *     revokedAt: string|null,
 *     expiresAt: string|null,
 *     createdAt: string|null,
 *     canRevoke: bool
 * }
 * @phpstan-type EditableClient array{
 *     id: int,
 *     name: string,
 *     clientId: string,
 *     redirectUris: array<int, string>,
 *     isActive: bool,
 *     scopes: array<int, string>,
 *     tokenPolicyId: int|null,
 *     trustTier: string,
 *     isFirstParty: bool,
 *     consentBypassAllowed: bool,
 *     secrets: array<int, ClientSecretView>
 * }
 */
class ClientService
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * Build the modal-first admin index payload for SSO clients.
     *
     * @param AdminClientFilters $filters
     * @return array{
     *     rows: array<int, ClientSummaryData>,
     *     scopeOptions: array<int, array{label: string, value: string, groupKey: string, groupLabel: string, action: string, itemLabel: string, helper: string}>,
     *     tokenPolicies: array<int, array{id: int, name: string}>,
     *     canManageClients: bool,
     *     filters: AdminClientFilters,
     *     sorting: array{field: string, order: int},
     *     pagination: array{currentPage: int, lastPage: int, perPage: int, total: int, from: int|null, to: int|null, first: int}
     * }
     */
    public function getIndexPayload(
        array $filters,
        int $perPage = 10,
        ?string $sortField = null,
        ?int $sortOrder = null,
        int $page = 1,
    ): array {
        $paginator = $this->clients->paginateForAdminIndex($filters, $sortField, $sortOrder, $perPage, $page);

        return [
            'rows' => Collection::make($paginator->items())
                ->map(fn (SsoClient $client) => ClientSummaryData::fromModel($client))
                ->values()
                ->all(),
            'scopeOptions' => ClientOptions::scopeOptions(),
            'tokenPolicies' => ClientOptions::tokenPolicies(),
            'canManageClients' => auth()->user()?->can(ClientPermissions::CREATE)
                || auth()->user()?->can(ClientPermissions::UPDATE)
                || auth()->user()?->can(ClientPermissions::DELETE)
                || false,
            'filters' => [
                'global' => $filters['global'] ?? null,
                'name' => $filters['name'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
            'sorting' => [
                'field' => $sortField ?? 'name',
                'order' => $sortOrder ?? 1,
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'first' => ($paginator->currentPage() - 1) * $paginator->perPage(),
            ],
        ];
    }

    /**
     * @return array{
     *     scopeOptions: array<int, array{label: string, value: string, groupKey: string, groupLabel: string, action: string, itemLabel: string, helper: string}>,
     *     tokenPolicies: array<int, array{id: int, name: string}>,
     *     trustTierOptions: array<int, array{label: string, value: string, helper: string}>,
     *     defaults: array{trustTier: string, isFirstParty: bool, consentBypassAllowed: bool}
     * }
     */
    public function getCreatePayload(): array
    {
        return [
            'scopeOptions' => ClientOptions::scopeOptions(),
            'tokenPolicies' => ClientOptions::tokenPolicies(),
            'trustTierOptions' => ClientOptions::trustTierOptions(),
            'defaults' => [
                'trustTier' => SsoClient::TRUST_TIER_THIRD_PARTY,
                'isFirstParty' => false,
                'consentBypassAllowed' => false,
            ],
        ];
    }

    /**
     * @return array{
     *     client: EditableClient,
     *     scopeOptions: array<int, array{label: string, value: string, groupKey: string, groupLabel: string, action: string, itemLabel: string, helper: string}>,
     *     tokenPolicies: array<int, array{id: int, name: string}>,
     *     trustTierOptions: array<int, array{label: string, value: string, helper: string}>,
     *     canManageSecrets: bool
     * }
     */
    public function getEditPayload(SsoClient $client): array
    {
        $client->loadMissing(['redirectUris', 'scopes', 'secrets']);

        return [
            'client' => $this->editableClient($client),
            'scopeOptions' => ClientOptions::scopeOptions(),
            'tokenPolicies' => ClientOptions::tokenPolicies(),
            'trustTierOptions' => ClientOptions::trustTierOptions(),
            'canManageSecrets' => auth()->user()?->can(ClientPermissions::MANAGE_SECRETS)
                || auth()->user()?->can(ClientPermissions::ROTATE_SECRET)
                || auth()->user()?->can(ClientPermissions::REVOKE_SECRET)
                || false,
        ];
    }

    /**
     * Persist a new SSO client and return the one-time plain secret for secure display.
     *
     * @param ClientWritePayload $payload
     * @return array{client: SsoClient, plainSecret: string}
     */
    public function createClient(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $plainSecret = Str::random(48);
            $redirectUris = $this->sanitizeUris($payload['redirect_uris'] ?? []);
            $scopeCodes = $this->sanitizeScopes($payload['scopes'] ?? []);

            $client = $this->clients->createClient([
                ...Arr::only($payload, ['name', 'is_active', 'token_policy_id', 'trust_tier', 'is_first_party', 'consent_bypass_allowed']),
                'client_id' => $this->generateClientId(),
                'client_secret_hash' => Hash::make($plainSecret),
                'redirect_uris' => $redirectUris,
                'scopes' => $scopeCodes,
            ]);

            $this->clients->syncRedirectUris($client, $redirectUris);
            $this->clients->syncScopes($client, $scopeCodes);
            $this->clients->createSecret($client, [
                'name' => 'Initial secret',
                'secret_hash' => Hash::make($plainSecret),
                'last_four' => Str::substr($plainSecret, -4),
                'is_active' => true,
            ]);

            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_ADMIN_CLIENT,
                event: 'admin.client.created',
                description: 'SSO client created.',
                subject: $client,
                causer: auth()->user(),
                properties: [
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'redirect_uri_count' => count($redirectUris),
                    'scope_codes' => $scopeCodes,
                    'policy_id' => $client->token_policy_id,
                    'trust_tier' => $client->trust_tier,
                    'is_first_party' => (bool) $client->is_first_party,
                    'consent_bypass_allowed' => (bool) $client->consent_bypass_allowed,
                    'status' => $client->is_active ? 'active' : 'inactive',
                ],
            );

            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_ADMIN_CLIENT,
                event: 'admin.client_secret.created',
                description: 'Initial client secret created.',
                subject: $client,
                causer: auth()->user(),
                properties: [
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'secret_last_four' => Str::substr($plainSecret, -4),
                ],
            );

            $this->logRedirectUriChanges($client, [], $redirectUris);
            $this->logClientScopeChanges($client, [], $scopeCodes);

            return [
                'client' => $client->fresh(['redirectUris', 'scopes', 'secrets']),
                'plainSecret' => $plainSecret,
            ];
        });
    }

    /**
     * Update an existing SSO client together with its redirect URIs and scope assignments.
     *
     * @param ClientWritePayload $payload
     */
    public function updateClient(SsoClient $client, array $payload): SsoClient
    {
        return DB::transaction(function () use ($client, $payload): SsoClient {
            $redirectUris = $this->sanitizeUris($payload['redirect_uris'] ?? []);
            $scopeCodes = $this->sanitizeScopes($payload['scopes'] ?? []);
            $previousRedirectUris = $client->normalizedRedirectUris();
            $previousScopeCodes = $client->normalizedScopeCodes();

            $updatedClient = $this->clients->updateClient($client, [
                ...Arr::only($payload, ['name', 'is_active', 'token_policy_id', 'trust_tier', 'is_first_party', 'consent_bypass_allowed']),
                'redirect_uris' => $redirectUris,
                'scopes' => $scopeCodes,
            ]);

            $this->clients->syncRedirectUris($updatedClient, $redirectUris);
            $this->clients->syncScopes($updatedClient, $scopeCodes);

            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_ADMIN_CLIENT,
                event: 'admin.client.updated',
                description: 'SSO client updated.',
                subject: $updatedClient,
                causer: auth()->user(),
                properties: [
                    'client_id' => $updatedClient->id,
                    'client_public_id' => $updatedClient->client_id,
                    'updated_fields' => array_values(array_keys(Arr::only($payload, ['name', 'is_active', 'token_policy_id', 'trust_tier', 'is_first_party', 'consent_bypass_allowed', 'redirect_uris', 'scopes']))),
                    'redirect_uri_count' => count($redirectUris),
                    'scope_codes' => $scopeCodes,
                    'policy_id' => $updatedClient->token_policy_id,
                    'trust_tier' => $updatedClient->trust_tier,
                    'is_first_party' => (bool) $updatedClient->is_first_party,
                    'consent_bypass_allowed' => (bool) $updatedClient->consent_bypass_allowed,
                    'status' => $updatedClient->is_active ? 'active' : 'inactive',
                ],
            );

            $this->logRedirectUriChanges($updatedClient, $previousRedirectUris, $redirectUris);
            $this->logClientScopeChanges($updatedClient, $previousScopeCodes, $scopeCodes);

            return $updatedClient->fresh(['redirectUris', 'scopes', 'secrets']);
        });
    }

    /**
     * Rotate the currently active client secret and return the newly issued plain secret once.
     *
     * @param ClientSecretPayload $payload
     * @return array{client: SsoClient, plainSecret: string}
     */
    public function rotateSecret(SsoClient $client, array $payload = []): array
    {
        return DB::transaction(function () use ($client, $payload): array {
            $plainSecret = Str::random(48);
            $secretName = trim((string) ($payload['name'] ?? '')) ?: 'Rotated secret '.now()->format('Y-m-d H:i');

            $this->clients->deactivateActiveSecrets($client);
            $this->clients->createSecret($client, [
                'name' => $secretName,
                'secret_hash' => Hash::make($plainSecret),
                'last_four' => Str::substr($plainSecret, -4),
                'is_active' => true,
            ]);

            $this->clients->updateClient($client, [
                'client_secret_hash' => Hash::make($plainSecret),
            ]);

            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_ADMIN_CLIENT,
                event: 'admin.client_secret.rotated',
                description: 'Client secret rotated.',
                subject: $client,
                causer: auth()->user(),
                properties: [
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'secret_last_four' => Str::substr($plainSecret, -4),
                ],
            );

            return [
                'client' => $client->fresh(['redirectUris', 'scopes', 'secrets']),
                'plainSecret' => $plainSecret,
            ];
        });
    }

    /**
     * Revoke one active client secret while preserving at least one usable secret.
     */
    public function revokeSecret(SsoClient $client, ClientSecret $secret): SsoClient
    {
        return DB::transaction(function () use ($client, $secret): SsoClient {
            abort_unless($secret->sso_client_id === $client->id, 404, 'Client secret not found.');

            if ($secret->revoked_at !== null || ! $secret->is_active) {
                throw ValidationException::withMessages([
                    'secret' => 'Client secret is already revoked.',
                ]);
            }

            if ($this->clients->countUsableSecrets($client) <= 1) {
                throw ValidationException::withMessages([
                    'secret' => 'Cannot revoke the last active client secret. Rotate the secret first.',
                ]);
            }

            $this->clients->revokeSecret($client, $secret->id);

            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_ADMIN_CLIENT,
                event: 'admin.client_secret.revoked',
                description: 'Client secret revoked.',
                subject: $client,
                causer: auth()->user(),
                properties: [
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'secret_last_four' => $secret->last_four,
                ],
            );

            return $client->fresh(['redirectUris', 'scopes', 'secrets']);
        });
    }

    /**
     * Delete an SSO client after its deletion has been audited.
     */
    public function deleteClient(SsoClient $client): void
    {
        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_ADMIN_CLIENT,
            event: 'admin.client.deleted',
            description: 'SSO client deleted.',
            subject: $client,
            causer: auth()->user(),
            properties: [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
            ],
        );

        $this->clients->deleteClient($client);
    }

    /**
     * Build the edit payload slice that the frontend form consumes for an existing client.
     *
     * @return EditableClient
     */
    public function editableClient(SsoClient $client): array
    {
        $usableSecrets = $client->secrets
            ->where('is_active', true)
            ->where('revoked_at', null)
            ->count();

        return [
            'id' => $client->id,
            'name' => $client->name,
            'clientId' => $client->client_id,
            'redirectUris' => $client->normalizedRedirectUris(),
            'isActive' => (bool) $client->is_active,
            'scopes' => $client->normalizedScopeCodes(),
            'tokenPolicyId' => $client->token_policy_id,
            'trustTier' => $client->trust_tier,
            'isFirstParty' => (bool) $client->is_first_party,
            'consentBypassAllowed' => (bool) $client->consent_bypass_allowed,
            'secrets' => $client->secrets
                ->map(fn (ClientSecret $secret): array => [
                    'id' => $secret->id,
                    'name' => $secret->name,
                    'lastFour' => $secret->last_four,
                    'isActive' => (bool) $secret->is_active,
                    'isRevoked' => $secret->revoked_at !== null,
                    'revokedAt' => $secret->revoked_at?->toDateTimeString(),
                    'expiresAt' => $secret->expires_at?->toDateTimeString(),
                    'createdAt' => $secret->created_at?->toDateTimeString(),
                    'canRevoke' => (bool) $secret->is_active && $secret->revoked_at === null && $usableSecrets > 1,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Normalize redirect URIs to a unique, trimmed string list.
     *
     * @param array<int, mixed> $uris
     * @return array<int, string>
     */
    private function sanitizeUris(array $uris): array
    {
        return collect($uris)
            ->map(fn ($uri) => trim((string) $uri))
            ->filter(fn (string $uri) => $uri !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Normalize scope codes and discard values that are not allowed by the current option set.
     *
     * @param array<int, mixed> $scopes
     * @return array<int, string>
     */
    private function sanitizeScopes(array $scopes): array
    {
        $allowed = ClientOptions::scopeValues();

        return collect($scopes)
            ->map(fn ($scope) => trim((string) $scope))
            ->filter(fn (string $scope) => in_array($scope, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Generate a compact random OAuth client identifier for newly created clients.
     */
    private function generateClientId(): string
    {
        return 'client_'.Str::lower(Str::random(24));
    }

    /**
     * @param array<int, string> $before
     * @param array<int, string> $after
     */
    private function logRedirectUriChanges(SsoClient $client, array $before, array $after): void
    {
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        foreach ($added as $redirectUri) {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_ADMIN_CLIENT,
                event: 'admin.redirect_uri.added',
                description: 'Client redirect URI added.',
                subject: $client,
                causer: auth()->user(),
                properties: [
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'redirect_uri' => $redirectUri,
                    'redirect_uri_count' => count($after),
                ],
            );
        }

        foreach ($removed as $redirectUri) {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_ADMIN_CLIENT,
                event: 'admin.redirect_uri.removed',
                description: 'Client redirect URI removed.',
                subject: $client,
                causer: auth()->user(),
                properties: [
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'redirect_uri' => $redirectUri,
                    'redirect_uri_count' => count($after),
                ],
            );
        }
    }

    /**
     * @param array<int, string> $before
     * @param array<int, string> $after
     */
    private function logClientScopeChanges(SsoClient $client, array $before, array $after): void
    {
        $assigned = array_values(array_diff($after, $before));
        $unassigned = array_values(array_diff($before, $after));

        if ($assigned !== []) {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_ADMIN_CLIENT,
                event: 'admin.client_scope.assigned',
                description: 'Client scopes assigned.',
                subject: $client,
                causer: auth()->user(),
                properties: [
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'scope_codes' => $assigned,
                ],
            );
        }

        if ($unassigned !== []) {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_ADMIN_CLIENT,
                event: 'admin.client_scope.unassigned',
                description: 'Client scopes unassigned.',
                subject: $client,
                causer: auth()->user(),
                properties: [
                    'client_id' => $client->id,
                    'client_public_id' => $client->client_id,
                    'scope_codes' => $unassigned,
                ],
            );
        }
    }
}
