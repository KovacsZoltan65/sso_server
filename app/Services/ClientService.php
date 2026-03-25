<?php

namespace App\Services;

use App\Data\ClientSummaryData;
use App\Models\SsoClient;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Support\ClientOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClientService
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
    ) {
    }

    /**
     * @return array<string, mixed>
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
            'canManageClients' => auth()->user()?->can('clients.create')
                || auth()->user()?->can('clients.update')
                || auth()->user()?->can('clients.delete')
                || auth()->user()?->can('sso-clients.manage')
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
     * @return array<string, mixed>
     */
    public function getCreatePayload(): array
    {
        return [
            'scopeOptions' => ClientOptions::scopeOptions(),
            'tokenPolicies' => ClientOptions::tokenPolicies(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getEditPayload(SsoClient $client): array
    {
        return [
            'client' => $this->editableClient($client),
            'scopeOptions' => ClientOptions::scopeOptions(),
            'tokenPolicies' => ClientOptions::tokenPolicies(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{client: SsoClient, plainSecret: string}
     */
    public function createClient(array $payload): array
    {
        $plainSecret = Str::random(48);

        $client = $this->clients->createClient([
            ...Arr::only($payload, ['name', 'is_active', 'token_policy_id']),
            'client_id' => $this->generateClientId(),
            'client_secret_hash' => Hash::make($plainSecret),
            'redirect_uris' => $this->sanitizeUris($payload['redirect_uris'] ?? []),
            'scopes' => $this->sanitizeScopes($payload['scopes'] ?? []),
        ]);

        return [
            'client' => $client,
            'plainSecret' => $plainSecret,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateClient(SsoClient $client, array $payload): SsoClient
    {
        return $this->clients->updateClient($client, [
            ...Arr::only($payload, ['name', 'is_active', 'token_policy_id']),
            'redirect_uris' => $this->sanitizeUris($payload['redirect_uris'] ?? []),
            'scopes' => $this->sanitizeScopes($payload['scopes'] ?? []),
        ]);
    }

    public function deleteClient(SsoClient $client): void
    {
        $this->clients->deleteClient($client);
    }

    /**
     * @return array<string, mixed>
     */
    public function editableClient(SsoClient $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'clientId' => $client->client_id,
            'redirectUris' => array_values($client->redirect_uris ?? []),
            'isActive' => (bool) $client->is_active,
            'scopes' => array_values($client->scopes ?? []),
            'tokenPolicyId' => $client->token_policy_id,
        ];
    }

    /**
     * @param array<int, mixed> $uris
     * @return array<int, string>
     */
    private function sanitizeUris(array $uris): array
    {
        return collect($uris)
            ->map(fn ($uri) => trim((string) $uri))
            ->filter(fn (string $uri) => $uri !== '')
            ->values()
            ->all();
    }

    /**
     * @param array<int, mixed> $scopes
     * @return array<int, string>
     */
    private function sanitizeScopes(array $scopes): array
    {
        $allowed = ClientOptions::scopeValues();

        return collect($scopes)
            ->map(fn ($scope) => trim((string) $scope))
            ->filter(fn (string $scope) => in_array($scope, $allowed, true))
            ->values()
            ->all();
    }

    private function generateClientId(): string
    {
        return 'client_'.Str::lower(Str::random(24));
    }
}
