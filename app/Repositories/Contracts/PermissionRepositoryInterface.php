<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Permission;

interface PermissionRepositoryInterface
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
    public function createPermission(array $attributes): Permission;

    /**
     * @param array<string, mixed> $attributes
     */
    public function updatePermission(Permission $permission, array $attributes): Permission;

    public function deletePermission(Permission $permission): void;

    public function hasAssignments(Permission $permission): bool;
}
