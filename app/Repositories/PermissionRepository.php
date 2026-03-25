<?php

namespace App\Repositories;

use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Prettus\Repository\Eloquent\Repository;
use Spatie\Permission\Models\Permission;

class PermissionRepository extends Repository implements PermissionRepositoryInterface
{
    /**
     * @var array<string, string>
     */
    private array $sortableFields = [
        'name' => 'name',
        'createdAt' => 'created_at',
    ];

    public function __construct(Permission $model)
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
            ->withCount('roles');

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

    public function createPermission(array $attributes): Permission
    {
        /** @var Permission $permission */
        $permission = $this->getModel()->newQuery()->create($attributes);

        return $permission;
    }

    public function updatePermission(Permission $permission, array $attributes): Permission
    {
        $permission->fill($attributes);
        $permission->save();

        return $permission->refresh();
    }

    public function deletePermission(Permission $permission): void
    {
        $permission->delete();
    }

    public function hasAssignments(Permission $permission): bool
    {
        $permissionId = $permission->getKey();

        $hasRoleAssignments = DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->exists();

        if ($hasRoleAssignments) {
            return true;
        }

        return DB::table('model_has_permissions')
            ->where('permission_id', $permissionId)
            ->exists();
    }
}
