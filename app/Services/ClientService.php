<?php

namespace App\Services;

use App\Data\ClientSummaryData;
use App\Models\ClientSecret;
use App\Models\SsoClient;
use App\Repositories\Contracts\ClientRepositoryInterface;
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
 *     secrets: array<int, ClientSecretView>
 * }
 */
class ClientService
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
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
     *     tokenPolicies: array<int, array{id: int, name: string}>
     * }
     */
    public function getCreatePayload(): array
    {
        return [
            'scopeOptions' => ClientOptions::scopeOptions(),
            'tokenPolicies' => ClientOptions::tokenPolicies(),
        ];
    }

    /**
     * @return array{
     *     client: EditableClient,
     *     scopeOptions: array<int, array{label: string, value: string, groupKey: string, groupLabel: string, action: string, itemLabel: string, helper: string}>,
     *     tokenPolicies: array<int, array{id: int, name: string}>,
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
            'canManageSecrets' => auth()->user()?->can(ClientPermissions::MANAGE_SECRETS)
                || auth()->user()?->can(ClientPermissions::ROTATE_SECRET)
                || auth()->user()?->can(ClientPermissions::REVOKE_SECRET)
                || false,
        ];
    }

    /**
     * Persist a new SSO client and return the one-time plain secret for secure display.
     *
     * @param array<string, mixed> $payload
     * @return array{client: SsoClient, plainSecret: string}
     */
    public function createClient(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $plainSecret = Str::random(48);
            $redirectUris = $this->sanitizeUris($payload['redirect_uris'] ?? []);
            $scopeCodes = $this->sanitizeScopes($payload['scopes'] ?? []);

            $client = $this->clients->createClient([
                ...Arr::only($payload, ['name', 'is_active', 'token_policy_id']),
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

            $this->logEvent(
                client: $client,
                event: 'client.created',
                message: 'SSO client created.',
                properties: [
                    'redirect_uris' => $redirectUris,
                    'scopes' => $scopeCodes,
                ],
            );

            $this->logEvent(
                client: $client,
                event: 'client.secret.created',
                message: 'Initial client secret created.',
                properties: ['type' => 'initial'],
            );

            return [
                'client' => $client->fresh(['redirectUris', 'scopes', 'secrets']),
                'plainSecret' => $plainSecret,
            ];
        });
    }

    /**
     * Update an existing SSO client together with its redirect URIs and scope assignments.
     *
     * @param array<string, mixed> $payload
     */
    public function updateClient(SsoClient $client, array $payload): SsoClient
    {
        return DB::transaction(function () use ($client, $payload): SsoClient {
            $redirectUris = $this->sanitizeUris($payload['redirect_uris'] ?? []);
            $scopeCodes = $this->sanitizeScopes($payload['scopes'] ?? []);

            $updatedClient = $this->clients->updateClient($client, [
                ...Arr::only($payload, ['name', 'is_active', 'token_policy_id']),
                'redirect_uris' => $redirectUris,
                'scopes' => $scopeCodes,
            ]);

            $this->clients->syncRedirectUris($updatedClient, $redirectUris);
            $this->clients->syncScopes($updatedClient, $scopeCodes);

            $this->logEvent(
                client: $updatedClient,
                event: 'client.updated',
                message: 'SSO client updated.',
                properties: [
                    'redirect_uris' => $redirectUris,
                    'scopes' => $scopeCodes,
                ],
            );

            return $updatedClient->fresh(['redirectUris', 'scopes', 'secrets']);
        });
    }

    /**
     * Rotate the currently active client secret and return the newly issued plain secret once.
     *
     * @param array<string, mixed> $payload
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

            $this->logEvent(
                client: $client,
                event: 'client.secret.rotated',
                message: 'Client secret rotated.',
                properties: ['name' => $secretName],
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

            $this->logEvent(
                client: $client,
                event: 'client.secret.revoked',
                message: 'Client secret revoked.',
                properties: [
                    'secret_id' => $secret->id,
                    'last_four' => $secret->last_four,
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
        $this->logEvent(
            client: $client,
            event: 'client.deleted',
            message: 'SSO client deleted.',
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
     * @param array<int, mixed> $uris
     * @return array<int, string>
     */
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
     * @param array<int, mixed> $scopes
     * @return array<int, string>
     */
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
     * Record an auditable client management event without leaking sensitive secret material.
     *
     * @param array<string, mixed> $properties
     */
    private function logEvent(SsoClient $client, string $event, string $message, array $properties = []): void
    {
        activity('admin')
            ->causedBy(auth()->user())
            ->performedOn($client)
            ->withProperties($properties)
            ->event($event)
            ->log($message);
    }
}
