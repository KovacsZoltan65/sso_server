<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface
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
     * @return Collection<int, string>
     */
    public function getPermissionNames(): Collection;

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, string> $permissions
     */
    public function createRole(array $attributes, array $permissions = []): Role;

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, string> $permissions
     */
    public function updateRole(Role $role, array $attributes, array $permissions = []): Role;

    public function deleteRole(Role $role): void;

    public function hasAssignedUsers(Role $role): bool;

    /**
     * @param array<int, int> $ids
     * @return Collection<int, Role>
     */
    public function getByIds(array $ids): Collection;

    /**
     * @param array<int, int> $ids
     */
    public function deleteByIds(array $ids): void;
}
