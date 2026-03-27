<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function permissionAbuseUser(array $abilities = []): User
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

it('forbids permission edit access when user only has viewAny permission', function () {
    $permission = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);
    $user = permissionAbuseUser(['permissions.viewAny']);

    $this->actingAs($user)
        ->get(route('admin.permissions.edit', $permission))
        ->assertForbidden();
});

it('forbids permission bulk delete when caller only has delete permission', function () {
    $permissions = collect([
        Permission::create(['name' => 'reports.export', 'guard_name' => 'web']),
        Permission::create(['name' => 'reports.download', 'guard_name' => 'web']),
    ]);
    $user = permissionAbuseUser(['permissions.delete']);

    $this->actingAs($user)
        ->deleteJson(route('admin.permissions.bulk-destroy'), [
            'ids' => $permissions->pluck('id')->all(),
        ])
        ->assertForbidden();

    foreach ($permissions as $permission) {
        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
        ]);
    }
});

it('returns not found when permission edit targets a non-existing id', function () {
    $user = permissionAbuseUser(['permissions.update']);

    $this->actingAs($user)
        ->get(route('admin.permissions.edit', 999999))
        ->assertNotFound();
});
