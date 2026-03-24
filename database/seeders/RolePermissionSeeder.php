<?php

namespace Database\Seeders;

use App\Support\SsoPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (SsoPermissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superadmin = Role::findOrCreate('superadmin', 'web');
        $admin = Role::findOrCreate('admin', 'web');

        $superadmin->syncPermissions(Permission::all());
        $admin->syncPermissions([
            'dashboard.view',
            'users.view',
            'roles.view',
            'permissions.view',
            'sso-clients.view',
            'scopes.view',
            'token-policies.view',
            'audit-logs.view',
        ]);
    }
}
