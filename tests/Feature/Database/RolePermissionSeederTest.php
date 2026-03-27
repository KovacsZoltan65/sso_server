<?php

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('seeds the standardized resource action permissions', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(Permission::where('name', 'users.viewAny')->where('guard_name', 'web')->exists())->toBeTrue();
    expect(Permission::where('name', 'users.deleteAny')->where('guard_name', 'web')->exists())->toBeTrue();
    expect(Permission::where('name', 'roles.assignPermission')->where('guard_name', 'web')->exists())->toBeTrue();
    expect(Permission::where('name', 'tokens.refreshToken')->where('guard_name', 'web')->exists())->toBeTrue();
    expect(Permission::where('name', 'clients.rotateSecret')->where('guard_name', 'web')->exists())->toBeTrue();
});

it('seeds only the standardized permission names', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(Permission::where('name', 'users.create')->where('guard_name', 'web')->exists())->toBeTrue();
    expect(Permission::where('name', 'clients.viewAny')->where('guard_name', 'web')->exists())->toBeTrue();
    expect(Permission::where('name', 'token-policies.deleteAny')->where('guard_name', 'web')->exists())->toBeTrue();
    expect(Permission::where('name', 'users.manage')->where('guard_name', 'web')->exists())->toBeFalse();
    expect(Permission::where('name', 'sso-clients.view')->where('guard_name', 'web')->exists())->toBeFalse();
    expect(Permission::where('name', 'token-policies.manage')->where('guard_name', 'web')->exists())->toBeFalse();
});

it('assigns every permission to superadmin and the standard admin set to admin', function () {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = Role::findByName('superadmin', 'web');
    $admin = Role::findByName('admin', 'web');
    $user = Role::findByName('user', 'web');

    expect($superadmin->permissions()->count())->toBe(Permission::count());
    expect($admin->hasPermissionTo('users.viewAny'))->toBeTrue();
    expect($admin->hasPermissionTo('users.deleteAny'))->toBeTrue();
    expect($admin->hasPermissionTo('users.assignRole'))->toBeTrue();
    expect($admin->hasPermissionTo('users.create'))->toBeTrue();
    expect($admin->hasPermissionTo('clients.viewAny'))->toBeTrue();
    expect($user->hasPermissionTo('dashboard.view'))->toBeTrue();
    expect($user->permissions()->pluck('name')->all())->toBe(['dashboard.view']);
});
