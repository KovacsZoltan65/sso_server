<?php

namespace App\Services;

use App\Data\PermissionSummaryData;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Support\Permissions\PermissionPermissions;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;
use Spatie\Permission\Models\Permission;

class PermissionService
{
    public function __construct(
        private readonly PermissionRepositoryInterface $permissions,
        private readonly AuditLogService $auditLogService,
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
                ->map(fn (Permission $permission) => PermissionSummaryData::fromModel(
                    permission: $permission,
                    canDelete: $this->canDeletePermission($permission),
                    deleteBlockCode: $this->deleteBlockCode($permission),
                    deleteBlockReason: $this->deleteBlockReason($permission),
                ))
                ->values()
                ->all(),
            'canManagePermissions' => auth()->user()?->can(PermissionPermissions::CREATE)
                || auth()->user()?->can(PermissionPermissions::UPDATE)
                || auth()->user()?->can(PermissionPermissions::DELETE)
                || auth()->user()?->can(PermissionPermissions::DELETE_ANY)
                || false,
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
        $permission = $this->permissions->createPermission([
            ...Arr::only($payload, ['name']),
            'guard_name' => 'web',
        ]);

        $this->auditLogService->logAdminCrud(
            resource: 'permission',
            action: 'created',
            description: 'Permission created.',
            subject: $permission,
            causer: auth()->user(),
            properties: [
                'target_permission_id' => $permission->id,
                'updated_fields' => ['name'],
            ],
        );

        return $permission;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updatePermission(Permission $permission, array $payload): Permission
    {
        $updatedPermission = $this->permissions->updatePermission($permission, [
            ...Arr::only($payload, ['name']),
            'guard_name' => 'web',
        ]);

        $this->auditLogService->logAdminCrud(
            resource: 'permission',
            action: 'updated',
            description: 'Permission updated.',
            subject: $updatedPermission,
            causer: auth()->user(),
            properties: [
                'target_permission_id' => $updatedPermission->id,
                'updated_fields' => array_values(array_keys(Arr::only($payload, ['name']))),
            ],
        );

        return $updatedPermission;
    }

    public function deletePermission(Permission $permission): void
    {
        $this->guardDeleteable($permission);

        $this->auditLogService->logAdminCrud(
            resource: 'permission',
            action: 'deleted',
            description: 'Permission deleted.',
            subject: $permission,
            causer: auth()->user(),
            properties: [
                'target_permission_id' => $permission->id,
            ],
        );

        $this->permissions->deletePermission($permission);
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    public function bulkDeletePermissions(array $ids): array
    {
        $permissions = $this->permissions->getByIds($ids);

        if ($permissions->count() !== count($ids)) {
            throw new RuntimeException('One or more selected permissions could not be found.');
        }

        foreach ($permissions as $permission) {
            $this->guardDeleteable($permission);
        }

        foreach ($permissions as $permission) {
            $this->auditLogService->logAdminCrud(
                resource: 'permission',
                action: 'deleted',
                description: 'Permission deleted.',
                subject: $permission,
                causer: auth()->user(),
                properties: [
                    'target_permission_id' => $permission->id,
                ],
            );
        }

        $deletedIds = $permissions->pluck('id')->values()->all();

        $this->permissions->deleteByIds($deletedIds);

        return $deletedIds;
    }

    public function canDeletePermission(Permission $permission): bool
    {
        return $this->deleteBlockCode($permission) === null;
    }

    private function guardDeleteable(Permission $permission): void
    {
        $reason = $this->deleteBlockReason($permission);

        if ($reason !== null) {
            throw new RuntimeException($reason);
        }
    }

    private function deleteBlockCode(Permission $permission): ?string
    {
        if (((int) ($permission->roles_count ?? 0) > 0) || ((int) ($permission->users_count ?? 0) > 0)) {
            return 'assigned_records';
        }

        if ($this->permissions->hasAssignments($permission)) {
            return 'assigned_records';
        }

        return null;
    }

    private function deleteBlockReason(Permission $permission): ?string
    {
        return match ($this->deleteBlockCode($permission)) {
            'assigned_records' => 'This permission is assigned to roles or users and cannot be deleted.',
            default => null,
        };
    }
}
