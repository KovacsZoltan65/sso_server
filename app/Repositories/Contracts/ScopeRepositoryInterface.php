<?php

namespace App\Repositories\Contracts;

use App\Models\Scope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ScopeRepositoryInterface
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
    public function createScope(array $attributes): Scope;

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateScope(Scope $scope, array $attributes): Scope;

    public function deleteScope(Scope $scope): void;

    /**
     * @param array<int, int> $ids
     * @return Collection<int, Scope>
     */
    public function getByIds(array $ids): Collection;

    /**
     * @param array<int, int> $ids
     */
    public function deleteByIds(array $ids): void;

    /**
     * @param array<int, string> $codes
     * @return array<string, int>
     */
    public function clientUsageCounts(array $codes): array;
}
