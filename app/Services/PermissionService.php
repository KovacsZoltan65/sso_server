<?php

namespace App\Services;

use App\Data\PermissionSummaryData;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;
use Spatie\Permission\Models\Permission;

class PermissionService
{
    public function __construct(
        private readonly PermissionRepositoryInterface $permissions,
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
        $paginator = $this->permissions->paginateForAdminIndex($filters, $sortField, $sortOrder, $perPage, $page);

        return [
            'rows' => Collection::make($paginator->items())
                ->map(fn (Permission $permission) => PermissionSummaryData::fromModel($permission))
                ->values()
                ->all(),
            'canManagePermissions' => auth()->user()?->can('permissions.manage') ?? false,
            'filters' => [
                'global' => $filters['global'] ?? null,
                'name' => $filters['name'] ?? null,
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
            'guardName' => 'web',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getEditPayload(Permission $permission): array
    {
        return [
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'guardName' => $permission->guard_name,
            ],
            'guardName' => $permission->guard_name,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createPermission(array $payload): Permission
    {
        return $this->permissions->createPermission([
            ...Arr::only($payload, ['name']),
            'guard_name' => 'web',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updatePermission(Permission $permission, array $payload): Permission
    {
        return $this->permissions->updatePermission($permission, [
            ...Arr::only($payload, ['name']),
            'guard_name' => 'web',
        ]);
    }

    public function deletePermission(Permission $permission): void
    {
        if ($this->permissions->hasAssignments($permission)) {
            throw new RuntimeException('This permission is assigned to roles or users and cannot be deleted.');
        }

        $this->permissions->deletePermission($permission);
    }
}
