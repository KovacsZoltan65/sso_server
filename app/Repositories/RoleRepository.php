<?php

namespace App\Repositories;

use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Prettus\Repository\Eloquent\Repository;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleRepository extends Repository implements RoleRepositoryInterface
{
    /**
     * @var array<string, string>
     */
    private array $sortableFields = [
        'name' => 'name',
        'createdAt' => 'created_at',
    ];

    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

    public function paginateForAdminIndex(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator {
        $query = $this->getModel()
            ->newQuery()
            ->where('guard_name', 'web')
            ->with(['permissions:id,name'])
            ->withCount(['permissions', 'users']);

        $global = trim((string) ($filters['global'] ?? ''));
        $name = trim((string) ($filters['name'] ?? ''));

        if ($global !== '') {
            $query->where(function ($innerQuery) use ($global): void {
                $innerQuery->where('name', 'like', "%{$global}%");
            });
        }

        if ($name !== '') {
            $query->where('name', 'like', "%{$name}%");
        }

        $column = $this->sortableFields[$sortField ?? ''] ?? 'name';
        $direction = $sortOrder === -1 ? 'desc' : 'asc';

        $query->orderBy($column, $direction);

        return $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }

    public function getPermissionNames(): Collection
    {
        /** @var Collection<int, string> $permissions */
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name');

        return $permissions;
    }

    public function createRole(array $attributes, array $permissions = []): Role
    {
        /** @var Role $role */
        $role = $this->getModel()->newQuery()->create($attributes);
        $role->syncPermissions($permissions);

        return $role->load('permissions');
    }

    public function updateRole(Role $role, array $attributes, array $permissions = []): Role
    {
        $role->fill($attributes);
        $role->save();
        $role->syncPermissions($permissions);

        return $role->load('permissions');
    }

    public function deleteRole(Role $role): void
    {
        $role->delete();
    }

    public function hasAssignedUsers(Role $role): bool
    {
        return $role->users()->exists();
    }
}
