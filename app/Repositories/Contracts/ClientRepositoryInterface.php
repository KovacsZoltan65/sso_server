<?php

namespace App\Repositories\Contracts;

use App\Models\SsoClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClientRepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginateForAdminIndex(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $attributes
     */
    public function createClient(array $attributes): SsoClient;

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateClient(SsoClient $client, array $attributes): SsoClient;

    /**
     * @param array<int, string> $redirectUris
     */
    public function syncRedirectUris(SsoClient $client, array $redirectUris): void;

    /**
     * @param array<int, string> $scopeCodes
     */
    public function syncScopes(SsoClient $client, array $scopeCodes): void;

    /**
     * @param array<string, mixed> $attributes
     */
    public function createSecret(SsoClient $client, array $attributes): void;

    public function deactivateActiveSecrets(SsoClient $client): void;

    public function revokeSecret(SsoClient $client, int $secretId): void;

    public function countUsableSecrets(SsoClient $client): int;

    public function deleteClient(SsoClient $client): void;
}
