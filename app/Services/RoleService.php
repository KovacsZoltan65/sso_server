<?php

namespace App\Services;

use App\Data\RoleSummaryData;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;

class RoleService
{
    /**
     * @var array<int, string>
     */
    private array $protectedRoles = ['superadmin', 'admin'];

    public function __construct(
        private readonly RoleRepositoryInterface $roles,
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
        $paginator = $this->roles->paginateForAdminIndex($filters, $sortField, $sortOrder, $perPage, $page);

        return [
            'rows' => Collection::make($paginator->items())
                ->map(fn (Role $role) => RoleSummaryData::fromModel(
                    role: $role,
                    canDelete: $this->canDeleteRole($role),
                    deleteBlockCode: $this->deleteBlockCode($role),
                    deleteBlockReason: $this->deleteBlockReason($role),
                ))
                ->values()
                ->all(),
            'canManageRoles' => auth()->user()?->can('roles.manage') ?? false,
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
            'permissionOptions' => $this->permissionOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getEditPayload(Role $role): array
    {
        return [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'guardName' => $role->guard_name,
                'permissions' => $role->permissions()->pluck('name')->values()->all(),
            ],
            'guardName' => $role->guard_name,
            'permissionOptions' => $this->permissionOptions(),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function permissionOptions(): array
    {
        return $this->roles->getPermissionNames()
            ->map(fn (string $permission) => [
                'label' => $permission,
                'value' => $permission,
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createRole(array $payload): Role
    {
        return DB::transaction(fn () => $this->roles->createRole(
            attributes: [
                ...Arr::only($payload, ['name']),
                'guard_name' => 'web',
            ],
            permissions: array_values($payload['permissions'] ?? []),
        ));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateRole(Role $role, array $payload): Role
    {
        return DB::transaction(fn () => $this->roles->updateRole(
            role: $role,
            attributes: [
                ...Arr::only($payload, ['name']),
                'guard_name' => 'web',
            ],
            permissions: array_values($payload['permissions'] ?? []),
        ));
    }

    public function deleteRole(Role $role): void
    {
        $this->guardDeleteable($role);

        $this->roles->deleteRole($role);
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    public function bulkDeleteRoles(array $ids): array
    {
        $roles = $this->roles->getByIds($ids);

        if ($roles->count() !== count($ids)) {
            throw new RuntimeException('One or more selected roles could not be found.');
        }

        foreach ($roles as $role) {
            $this->guardDeleteable($role);
        }

        $deletedIds = $roles->pluck('id')->values()->all();

        $this->roles->deleteByIds($deletedIds);

        return $deletedIds;
    }

    public function canDeleteRole(Role $role): bool
    {
        return $this->deleteBlockCode($role) === null;
    }

    private function guardDeleteable(Role $role): void
    {
        $reason = $this->deleteBlockReason($role);

        if ($reason !== null) {
            throw new RuntimeException($reason);
        }
    }

    private function deleteBlockCode(Role $role): ?string
    {
        if (in_array($role->name, $this->protectedRoles, true)) {
            return 'protected_role';
        }

        if ((int) ($role->users_count ?? 0) > 0 || $this->roles->hasAssignedUsers($role)) {
            return 'assigned_users';
        }

        return null;
    }

    private function deleteBlockReason(Role $role): ?string
    {
        return match ($this->deleteBlockCode($role)) {
            'protected_role' => 'This role is protected and cannot be deleted.',
            'assigned_users' => 'This role is assigned to users and cannot be deleted.',
            default => null,
        };
    }
}
