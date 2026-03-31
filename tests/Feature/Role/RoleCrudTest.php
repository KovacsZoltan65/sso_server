<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function roleUser(array $abilities = []): User
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

it('authorized user can view role index', function () {
    Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);

    $user = roleUser(['roles.viewAny']);

    $this->actingAs($user)
        ->get(route('admin.roles.index', ['global' => 'auditor']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Roles/Index')
            ->has('rows', 1)
            ->where('rows.0.name', 'auditor')
            ->where('rows.0.permissions', [])
            ->where('rows.0.usersCount', 0)
            ->where('canManageRoles', false));
});

it('unauthorized user is forbidden from role index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.roles.index'))
        ->assertForbidden();
});

it('authorized user can view role create page', function () {
    Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);

    $user = roleUser(['roles.create']);

    $this->actingAs($user)
        ->get(route('admin.roles.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Roles/Create')
            ->where('guardName', 'web')
            ->has('permissionOptions', 2)
            ->where('permissionOptions.0.value', 'reports.view')
            ->where('permissionOptions.1.value', 'roles.create'));
});

it('authorized user can store role with permissions', function () {
    Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);

    $user = roleUser(['roles.create']);

    $this->actingAs($user)
        ->post(route('admin.roles.store'), [
            'name' => 'auditor',
            'permissions' => ['reports.view', 'reports.export'],
        ])
        ->assertRedirect(route('admin.roles.index'))
        ->assertSessionHas('success', 'Role created successfully.');

    $role = Role::findByName('auditor', 'web');

    expect($role->hasPermissionTo('reports.view'))->toBeTrue();
    expect($role->hasPermissionTo('reports.export'))->toBeTrue();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'admin.role',
        'event' => 'admin.role.created',
        'causer_id' => $user->id,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'admin.role',
        'event' => 'admin.role.permission_attached',
        'causer_id' => $user->id,
    ]);
});

it('store validation fails for invalid role payload', function () {
    Role::create(['name' => 'auditor', 'guard_name' => 'web']);

    $user = roleUser(['roles.create']);

    $this->actingAs($user)
        ->from(route('admin.roles.create'))
        ->post(route('admin.roles.store'), [
            'name' => '',
        ])
        ->assertRedirect(route('admin.roles.create'))
        ->assertSessionHasErrors(['name']);
});

it('authorized user can view role edit page', function () {
    $permission = Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);
    $user = roleUser(['roles.update']);

    $this->actingAs($user)
        ->get(route('admin.roles.edit', $role))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Roles/Edit')
            ->where('role.id', $role->id)
            ->where('role.name', 'auditor')
            ->where('role.permissions.0', 'reports.view'));
});

it('authorized user can update role and sync permissions', function () {
    Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    $role->givePermissionTo('reports.view');
    $user = roleUser(['roles.update']);

    $this->actingAs($user)
        ->put(route('admin.roles.update', $role), [
            'name' => 'reviewer',
            'permissions' => ['reports.export'],
        ])
        ->assertRedirect(route('admin.roles.index'))
        ->assertSessionHas('success', 'Role updated successfully.');

    $role->refresh();

    expect($role->name)->toBe('reviewer');
    expect($role->hasPermissionTo('reports.export'))->toBeTrue();
    expect($role->hasPermissionTo('reports.view'))->toBeFalse();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'admin.role',
        'event' => 'admin.role.updated',
        'causer_id' => $user->id,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'admin.role',
        'event' => 'admin.role.permission_detached',
        'causer_id' => $user->id,
    ]);
});

it('update validation fails for invalid role payload', function () {
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    Role::create(['name' => 'reviewer', 'guard_name' => 'web']);
    $user = roleUser(['roles.update']);

    $this->actingAs($user)
        ->from(route('admin.roles.edit', $role))
        ->put(route('admin.roles.update', $role), [
            'name' => 'reviewer',
        ])
        ->assertRedirect(route('admin.roles.edit', $role))
        ->assertSessionHasErrors(['name']);
});

it('authorized user can delete unassigned role', function () {
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    $user = roleUser(['roles.delete']);

    $this->actingAs($user)
        ->delete(route('admin.roles.destroy', $role))
        ->assertRedirect(route('admin.roles.index'))
        ->assertSessionHas('success', 'Role deleted successfully.');

    $this->assertDatabaseMissing('roles', [
        'id' => $role->id,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'admin.role',
        'event' => 'admin.role.deleted',
        'causer_id' => $user->id,
    ]);
});

it('forbids role delete when unauthorized', function () {
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('admin.roles.destroy', $role))
        ->assertForbidden();
});

it('prevents deleting protected roles', function () {
    $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $user = roleUser(['roles.delete']);

    $this->actingAs($user)
        ->delete(route('admin.roles.destroy', $role))
        ->assertRedirect(route('admin.roles.index'))
        ->assertSessionHas('error', 'This role is protected and cannot be deleted.');

    $this->assertDatabaseHas('roles', [
        'id' => $role->id,
    ]);
});

it('prevents deleting role assigned to users', function () {
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    $assignedUser = User::factory()->create();
    $assignedUser->assignRole($role);
    $user = roleUser(['roles.delete']);

    $this->actingAs($user)
        ->delete(route('admin.roles.destroy', $role))
        ->assertRedirect(route('admin.roles.index'))
        ->assertSessionHas('error', 'This role is assigned to users and cannot be deleted.');

    $this->assertDatabaseHas('roles', [
        'id' => $role->id,
    ]);
});

it('authorized user can bulk delete unassigned roles', function () {
    $roles = collect([
        Role::create(['name' => 'auditor', 'guard_name' => 'web']),
        Role::create(['name' => 'reviewer', 'guard_name' => 'web']),
    ]);
    $user = roleUser(['roles.deleteAny']);

    $this->actingAs($user)
        ->deleteJson(route('admin.roles.bulk-destroy'), [
            'ids' => $roles->pluck('id')->all(),
        ])
        ->assertOk()
        ->assertJson([
            'message' => 'Selected roles deleted successfully.',
            'meta' => [
                'deletedCount' => 2,
            ],
        ]);

    foreach ($roles as $role) {
        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
        ]);
    }
});

it('blocks bulk delete when a protected role is selected', function () {
    $roles = collect([
        Role::create(['name' => 'admin', 'guard_name' => 'web']),
        Role::create(['name' => 'auditor', 'guard_name' => 'web']),
    ]);
    $user = roleUser(['roles.deleteAny']);

    $this->actingAs($user)
        ->deleteJson(route('admin.roles.bulk-destroy'), [
            'ids' => $roles->pluck('id')->all(),
        ])
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'This role is protected and cannot be deleted.',
        ]);

    foreach ($roles as $role) {
        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
        ]);
    }
});

it('forbids role bulk delete when unauthorized', function () {
    $roles = collect([
        Role::create(['name' => 'auditor', 'guard_name' => 'web']),
        Role::create(['name' => 'reviewer', 'guard_name' => 'web']),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('admin.roles.bulk-destroy'), [
            'ids' => $roles->pluck('id')->all(),
        ])
        ->assertForbidden();
});
