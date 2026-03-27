<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @return array<string, array<int, string>>
     */
    private function permissionMappings(): array
    {
        return [
            'users.view' => ['users.viewAny'],
            'roles.view' => ['roles.viewAny'],
            'permissions.view' => ['permissions.viewAny'],
            'scopes.view' => ['scopes.viewAny'],
            'token-policies.view' => ['token-policies.viewAny'],
            'audit-logs.view' => ['audit-logs.viewAny'],
            'redirect-uris.view' => ['redirect-uris.viewAny'],
            'secrets.view' => ['secrets.viewAny'],
            'sso-clients.view' => ['clients.viewAny', 'clients.view'],
            'users.manage' => ['users.create', 'users.update', 'users.delete', 'users.deleteAny'],
            'roles.manage' => ['roles.create', 'roles.update', 'roles.delete', 'roles.deleteAny'],
            'permissions.manage' => ['permissions.create', 'permissions.update', 'permissions.delete', 'permissions.deleteAny'],
            'scopes.manage' => ['scopes.create', 'scopes.update', 'scopes.delete', 'scopes.deleteAny'],
            'token-policies.manage' => ['token-policies.create', 'token-policies.update', 'token-policies.delete', 'token-policies.deleteAny'],
            'redirect-uris.manage' => ['redirect-uris.create', 'redirect-uris.update', 'redirect-uris.delete', 'redirect-uris.deleteAny'],
            'secrets.manage' => ['secrets.create', 'secrets.update', 'secrets.delete', 'secrets.deleteAny'],
            'sso-clients.manage' => [
                'clients.create',
                'clients.update',
                'clients.delete',
                'clients.manageSecrets',
                'clients.rotateSecret',
                'clients.revokeSecret',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function removableLegacyPermissions(): array
    {
        return [
            'users.manage',
            'roles.manage',
            'permissions.manage',
            'sso-clients.view',
            'sso-clients.manage',
            'redirect-uris.manage',
            'scopes.manage',
            'secrets.manage',
            'token-policies.manage',
        ];
    }

    public function up(): void
    {
        $permissionsTable = config('permission.table_names.permissions', 'permissions');
        $roleHasPermissionsTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $modelHasPermissionsTable = config('permission.table_names.model_has_permissions', 'model_has_permissions');

        DB::transaction(function () use ($permissionsTable, $roleHasPermissionsTable, $modelHasPermissionsTable): void {
            foreach ($this->permissionMappings() as $sourcePermission => $targetPermissions) {
                $source = DB::table($permissionsTable)
                    ->where('name', $sourcePermission)
                    ->where('guard_name', 'web')
                    ->first(['id']);

                if ($source === null) {
                    continue;
                }

                $roleAssignments = DB::table($roleHasPermissionsTable)
                    ->where('permission_id', $source->id)
                    ->get();

                $modelAssignments = DB::table($modelHasPermissionsTable)
                    ->where('permission_id', $source->id)
                    ->get();

                foreach ($targetPermissions as $targetPermission) {
                    $targetId = DB::table($permissionsTable)->updateOrInsert(
                        ['name' => $targetPermission, 'guard_name' => 'web'],
                        [],
                    )
                        ? DB::table($permissionsTable)
                            ->where('name', $targetPermission)
                            ->where('guard_name', 'web')
                            ->value('id')
                        : null;

                    if ($targetId === null) {
                        continue;
                    }

                    foreach ($roleAssignments as $assignment) {
                        $exists = DB::table($roleHasPermissionsTable)
                            ->where('permission_id', $targetId)
                            ->where('role_id', $assignment->role_id)
                            ->exists();

                        if (! $exists) {
                            DB::table($roleHasPermissionsTable)->insert([
                                'permission_id' => $targetId,
                                'role_id' => $assignment->role_id,
                            ]);
                        }
                    }

                    foreach ($modelAssignments as $assignment) {
                        $exists = DB::table($modelHasPermissionsTable)
                            ->where('permission_id', $targetId)
                            ->where('model_type', $assignment->model_type)
                            ->where('model_id', $assignment->model_id)
                            ->exists();

                        if (! $exists) {
                            DB::table($modelHasPermissionsTable)->insert([
                                'permission_id' => $targetId,
                                'model_type' => $assignment->model_type,
                                'model_id' => $assignment->model_id,
                            ]);
                        }
                    }
                }
            }

            $legacyIds = DB::table($permissionsTable)
                ->where('guard_name', 'web')
                ->whereIn('name', $this->removableLegacyPermissions())
                ->pluck('id')
                ->all();

            if ($legacyIds !== []) {
                DB::table($roleHasPermissionsTable)->whereIn('permission_id', $legacyIds)->delete();
                DB::table($modelHasPermissionsTable)->whereIn('permission_id', $legacyIds)->delete();
                DB::table($permissionsTable)->whereIn('id', $legacyIds)->delete();
            }
        });
    }

    public function down(): void
    {
        $permissionsTable = config('permission.table_names.permissions', 'permissions');

        DB::table($permissionsTable)
            ->where('guard_name', 'web')
            ->whereIn('name', $this->removableLegacyPermissions())
            ->delete();
    }
};
