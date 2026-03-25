<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function permissionUser(array $abilities = []): User
{
    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        Permission::findOrCreate($ability, 'web');
    }

    if ($abilities !== []) {
        $user->givePermissionTo($abilities);
    }

    return $user;
}

it('authorized user can view permission index', function () {
    Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);

    $user = permissionUser(['permissions.view']);

    $this->actingAs($user)
        ->get(route('admin.permissions.index', ['global' => 'reports.view']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Permissions/Index')
            ->has('rows', 1)
            ->where('rows.0.name', 'reports.view')
            ->where('rows.0.guardName', 'web')
            ->where('rows.0.rolesCount', 0)
            ->where('filters.global', 'reports.view')
            ->where('sorting.field', 'name')
            ->where('pagination.currentPage', 1)
            ->where('canManagePermissions', false));
});

it('unauthorized user is forbidden from permission index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.permissions.index'))
        ->assertForbidden();
});

it('authorized user can view permission create page', function () {
    $user = permissionUser(['permissions.manage']);

    $this->actingAs($user)
        ->get(route('admin.permissions.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Permissions/Create')
            ->where('guardName', 'web'));
});

it('authorized user can store permission', function () {
    $user = permissionUser(['permissions.manage']);

    $this->actingAs($user)
        ->post(route('admin.permissions.store'), [
            'name' => 'reports.export',
        ])
        ->assertRedirect(route('admin.permissions.index'))
        ->assertSessionHas('success', 'Permission created successfully.');

    $this->assertDatabaseHas('permissions', [
        'name' => 'reports.export',
        'guard_name' => 'web',
    ]);
});

it('store validation fails for invalid permission payload', function () {
    Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);

    $user = permissionUser(['permissions.manage']);

    $this->actingAs($user)
        ->from(route('admin.permissions.create'))
        ->post(route('admin.permissions.store'), [
            'name' => '',
        ])
        ->assertRedirect(route('admin.permissions.create'))
        ->assertSessionHasErrors(['name']);
});

it('authorized user can view permission edit page', function () {
    $permission = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);
    $user = permissionUser(['permissions.manage']);

    $this->actingAs($user)
        ->get(route('admin.permissions.edit', $permission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Permissions/Edit')
            ->where('permission.id', $permission->id)
            ->where('permission.name', 'reports.export'));
});

it('authorized user can update permission', function () {
    $permission = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);
    $user = permissionUser(['permissions.manage']);

    $this->actingAs($user)
        ->put(route('admin.permissions.update', $permission), [
            'name' => 'reports.download',
        ])
        ->assertRedirect(route('admin.permissions.index'))
        ->assertSessionHas('success', 'Permission updated successfully.');

    $this->assertDatabaseHas('permissions', [
        'id' => $permission->id,
        'name' => 'reports.download',
        'guard_name' => 'web',
    ]);
});

it('update validation fails for invalid permission payload', function () {
    $permission = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);
    Permission::create(['name' => 'reports.download', 'guard_name' => 'web']);
    $user = permissionUser(['permissions.manage']);

    $this->actingAs($user)
        ->from(route('admin.permissions.edit', $permission))
        ->put(route('admin.permissions.update', $permission), [
            'name' => 'reports.download',
        ])
        ->assertRedirect(route('admin.permissions.edit', $permission))
        ->assertSessionHasErrors(['name']);
});

it('authorized user can delete unassigned permission', function () {
    $permission = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);
    $user = permissionUser(['permissions.manage']);

    $this->actingAs($user)
        ->delete(route('admin.permissions.destroy', $permission))
        ->assertRedirect(route('admin.permissions.index'))
        ->assertSessionHas('success', 'Permission deleted successfully.');

    $this->assertDatabaseMissing('permissions', [
        'id' => $permission->id,
    ]);
});

it('forbids permission delete when unauthorized', function () {
    $permission = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('admin.permissions.destroy', $permission))
        ->assertForbidden();
});

it('prevents deleting permission that is assigned to a role or user', function () {
    $permission = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);
    $manager = permissionUser(['permissions.manage']);
    $assignedUser = User::factory()->create();
    $assignedUser->givePermissionTo($permission);

    $this->actingAs($manager)
        ->delete(route('admin.permissions.destroy', $permission))
        ->assertRedirect(route('admin.permissions.index'))
        ->assertSessionHas('error', 'This permission is assigned to roles or users and cannot be deleted.');

    $this->assertDatabaseHas('permissions', [
        'id' => $permission->id,
    ]);
});

it('authorized user can bulk delete unassigned permissions', function () {
    $permissions = collect([
        Permission::create(['name' => 'reports.export', 'guard_name' => 'web']),
        Permission::create(['name' => 'reports.download', 'guard_name' => 'web']),
    ]);
    $manager = permissionUser(['permissions.manage']);

    $this->actingAs($manager)
        ->deleteJson(route('admin.permissions.bulk-destroy'), [
            'ids' => $permissions->pluck('id')->all(),
        ])
        ->assertOk()
        ->assertJson([
            'message' => 'Selected permissions deleted successfully.',
            'meta' => [
                'deletedCount' => 2,
            ],
        ]);

    foreach ($permissions as $permission) {
        $this->assertDatabaseMissing('permissions', [
            'id' => $permission->id,
        ]);
    }
});

it('blocks bulk delete when an assigned permission is selected', function () {
    $assignedPermission = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);
    $freePermission = Permission::create(['name' => 'reports.download', 'guard_name' => 'web']);
    $assignedUser = User::factory()->create();
    $assignedUser->givePermissionTo($assignedPermission);
    $manager = permissionUser(['permissions.manage']);

    $this->actingAs($manager)
        ->deleteJson(route('admin.permissions.bulk-destroy'), [
            'ids' => [$assignedPermission->id, $freePermission->id],
        ])
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'This permission is assigned to roles or users and cannot be deleted.',
        ]);

    $this->assertDatabaseHas('permissions', [
        'id' => $assignedPermission->id,
    ]);
    $this->assertDatabaseHas('permissions', [
        'id' => $freePermission->id,
    ]);
});

it('forbids permission bulk delete when unauthorized', function () {
    $permissions = collect([
        Permission::create(['name' => 'reports.export', 'guard_name' => 'web']),
        Permission::create(['name' => 'reports.download', 'guard_name' => 'web']),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('admin.permissions.bulk-destroy'), [
            'ids' => $permissions->pluck('id')->all(),
        ])
        ->assertForbidden();
});
