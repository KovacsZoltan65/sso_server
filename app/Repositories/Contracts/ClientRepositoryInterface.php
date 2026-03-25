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

    public function deleteClient(SsoClient $client): void;
}
