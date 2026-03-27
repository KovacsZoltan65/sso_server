<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('allows self-service profile update for permitted profile fields only', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Permission::findOrCreate('users.update', 'web');

    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Updated Self',
            'email' => 'updated-self@example.com',
            'roles' => ['admin'],
            'permissions' => ['users.update'],
            'password' => 'new-password',
        ])
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Updated Self');
    expect($user->email)->toBe('updated-self@example.com');
    expect($user->hasRole('admin'))->toBeFalse();
    expect($user->can('users.update'))->toBeFalse();
    expect(Hash::check('password', $user->password))->toBeTrue();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'admin',
        'event' => 'profile.updated',
        'causer_id' => $user->id,
        'causer_type' => User::class,
    ]);
});

it('redirects guests away from profile routes', function () {
    $this->get(route('profile.edit'))
        ->assertRedirect(route('login'));

    $this->patch(route('profile.update'), [
        'name' => 'Guest Update',
        'email' => 'guest@example.com',
    ])->assertRedirect(route('login'));
});

it('forbids using the admin user update route on self without admin permission', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('admin.users.update', $user), [
            'name' => 'Escalation Attempt',
            'email' => $user->email,
            'roles' => ['admin'],
        ])
        ->assertForbidden();

    expect($user->fresh()->hasRole('admin'))->toBeFalse();
});

it('returns not found when a profile user tries to target another user through the admin route', function () {
    $actor = User::factory()->create();

    $this->actingAs($actor)
        ->put(route('admin.users.update', 999999), [
            'name' => 'Missing Target',
            'email' => 'missing-target@example.com',
            'roles' => [],
        ])
        ->assertNotFound();
});
