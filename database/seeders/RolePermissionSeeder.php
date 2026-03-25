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
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $superadmin = Role::findOrCreate('superadmin', 'web');
        $admin = Role::findOrCreate('admin', 'web');
        $user = Role::findOrCreate('user', 'web');

        $superadmin->syncPermissions(Permission::all());
        $admin->syncPermissions(SsoPermissions::adminPermissions());
        $user->syncPermissions([
            'dashboard.view',
        ]);
    }
}
