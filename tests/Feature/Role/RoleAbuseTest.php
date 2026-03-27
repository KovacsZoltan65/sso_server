<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function roleAbuseUser(array $abilities = []): User
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

it('forbids role edit access when user only has viewAny permission', function () {
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    $user = roleAbuseUser(['roles.viewAny']);

    $this->actingAs($user)
        ->get(route('admin.roles.edit', $role))
        ->assertForbidden();
});

it('forbids role bulk delete when caller only has delete permission', function () {
    $roles = collect([
        Role::create(['name' => 'auditor', 'guard_name' => 'web']),
        Role::create(['name' => 'reviewer', 'guard_name' => 'web']),
    ]);
    $user = roleAbuseUser(['roles.delete']);

    $this->actingAs($user)
        ->deleteJson(route('admin.roles.bulk-destroy'), [
            'ids' => $roles->pluck('id')->all(),
        ])
        ->assertForbidden();

    foreach ($roles as $role) {
        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
        ]);
    }
});

it('returns not found when role edit targets a non-existing id', function () {
    $user = roleAbuseUser(['roles.update']);

    $this->actingAs($user)
        ->get(route('admin.roles.edit', 999999))
        ->assertNotFound();
});
