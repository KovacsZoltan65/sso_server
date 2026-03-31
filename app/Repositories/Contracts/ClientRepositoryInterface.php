<?php

namespace App\Repositories\Contracts;

use App\Models\SsoClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @phpstan-type AdminClientFilters array{
 *     global?: string|null,
 *     status?: string|null
 * }
 * @phpstan-type ClientAttributes array{
 *     name: string,
 *     client_id?: string,
 *     client_secret_hash?: string,
 *     is_active: bool,
 *     token_policy_id?: int|null,
 *     redirect_uris?: array<int, string>,
 *     scopes?: array<int, string>
 * }
 * @phpstan-type ClientSecretAttributes array{
 *     name: string,
 *     secret_hash: string,
 *     last_four: string,
 *     is_active: bool
 * }
 */
interface ClientRepositoryInterface
{
    /**
     * @param AdminClientFilters $filters
     */
    public function paginateForAdminIndex(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator;

    /**
     * @param ClientAttributes $attributes
     */
    public function createClient(array $attributes): SsoClient;

    /**
     * @param ClientAttributes $attributes
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
     * @param ClientSecretAttributes $attributes
     */
    public function createSecret(SsoClient $client, array $attributes): void;

    public function deactivateActiveSecrets(SsoClient $client): void;

    public function revokeSecret(SsoClient $client, int $secretId): void;

    public function countUsableSecrets(SsoClient $client): int;

    public function deleteClient(SsoClient $client): void;
}
