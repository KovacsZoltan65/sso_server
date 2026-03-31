<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function userAbuseManager(array $abilities = []): User
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

it('forbids self role escalation through the admin update endpoint without users.update', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $user = userAbuseManager();

    $this->actingAs($user)
        ->put(route('admin.users.update', $user), [
            'name' => 'Escalation Attempt',
            'email' => $user->email,
            'roles' => ['admin'],
        ])
        ->assertForbidden();

    expect($user->fresh()->name)->not->toBe('Escalation Attempt');
    expect($user->fresh()->hasRole('admin'))->toBeFalse();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'security',
        'event' => 'security.authorization.denied',
        'causer_id' => $user->id,
        'causer_type' => User::class,
    ]);
});

it('forbids user bulk delete when caller only has delete permission but not deleteAny', function () {
    $targets = User::factory()->count(2)->create();
    $user = userAbuseManager(['users.delete']);

    $this->actingAs($user)
        ->deleteJson(route('admin.users.bulk-destroy'), [
            'ids' => $targets->pluck('id')->all(),
        ])
        ->assertForbidden();

    foreach ($targets as $target) {
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
        ]);
    }
});

it('returns not found when user update targets a non-existing id', function () {
    $user = userAbuseManager(['users.update']);

    $this->actingAs($user)
        ->put(route('admin.users.update', 999999), [
            'name' => 'Missing User',
            'email' => 'missing-user@example.com',
            'roles' => [],
        ])
        ->assertNotFound();
});
