<?php

namespace App\Repositories\Contracts;

use App\Models\TokenPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TokenPolicyRepositoryInterface
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
    public function createTokenPolicy(array $attributes): TokenPolicy;

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateTokenPolicy(TokenPolicy $tokenPolicy, array $attributes): TokenPolicy;

    public function deleteTokenPolicy(TokenPolicy $tokenPolicy): void;

    /**
     * @param array<int, int> $ids
     * @return Collection<int, TokenPolicy>
     */
    public function getByIds(array $ids): Collection;

    /**
     * @param array<int, int> $ids
     */
    public function deleteByIds(array $ids): void;

    public function clearDefaultFlagExcept(?int $exceptId = null): void;

    /**
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    public function clientUsageCounts(array $ids): array;
}
