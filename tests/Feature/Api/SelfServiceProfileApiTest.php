<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('allows an authenticated user to fetch their own self-service profile via api', function (): void {
    $user = User::factory()->create([
        'name' => 'Profile User',
        'email' => 'profile@example.test',
    ]);

    $this->actingAs($user)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('message', 'Profile retrieved successfully.')
        ->assertJsonPath('data.name', 'Profile User')
        ->assertJsonPath('data.email', 'profile@example.test')
        ->assertJsonPath('meta.editable_fields.0', 'name')
        ->assertJsonPath('meta.read_only_fields.0', 'email')
        ->assertJsonPath('errors', []);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'account',
        'event' => 'account.profile.viewed',
        'causer_id' => $user->id,
        'causer_type' => User::class,
    ]);
});

it('rejects guest access to the self-service profile api', function (): void {
    $this->getJson('/api/profile')
        ->assertUnauthorized()
        ->assertExactJson([
            'message' => 'Authentication failed.',
            'data' => [],
            'meta' => [
                'redirect_to' => route('login'),
                'reauth_to' => route('login'),
            ],
            'errors' => [],
        ]);
});

it('returns the self-service reauth contract for guest profile update requests', function (): void {
    $this->patchJson('/api/profile', [
        'name' => 'Guest User',
    ])
        ->assertUnauthorized()
        ->assertExactJson([
            'message' => 'Authentication failed.',
            'data' => [],
            'meta' => [
                'redirect_to' => route('login'),
                'reauth_to' => route('login'),
            ],
            'errors' => [],
        ]);
});

it('returns the self-service reauth contract for guest profile password requests', function (): void {
    $this->patchJson('/api/profile/password', [
        'current_password' => 'password',
        'password' => 'a-stronger-password',
        'password_confirmation' => 'a-stronger-password',
    ])
        ->assertUnauthorized()
        ->assertExactJson([
            'message' => 'Authentication failed.',
            'data' => [],
            'meta' => [
                'redirect_to' => route('login'),
                'reauth_to' => route('login'),
            ],
            'errors' => [],
        ]);
});

it('updates only whitelisted self-service profile fields', function (): void {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.test',
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->patchJson('/api/profile', [
            'name' => 'Updated Name',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Profile updated successfully.')
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.email', 'original@example.test')
        ->assertJsonPath('errors', []);

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('original@example.test');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'account',
        'event' => 'account.profile.updated',
        'causer_id' => $user->id,
        'causer_type' => User::class,
    ]);
});

it('does not write a success audit event when the self-service profile update is a no-op', function (): void {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.test',
    ]);

    $existingUpdateCount = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'account')
        ->where('event', 'account.profile.updated')
        ->count();

    $this->actingAs($user)
        ->patchJson('/api/profile', [
            'name' => 'Original Name',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Profile updated successfully.')
        ->assertJsonPath('data.name', 'Original Name')
        ->assertJsonPath('errors', []);

    expect(\Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'account')
        ->where('event', 'account.profile.updated')
        ->count())->toBe($existingUpdateCount);
});

it('rejects forbidden self-service mutation fields instead of silently accepting them', function (): void {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.test',
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->patchJson('/api/profile', [
            'name' => 'Updated Name',
            'email' => 'changed@example.test',
            'roles' => ['admin'],
            'permissions' => ['users.update'],
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Validation failed.')
        ->assertJsonValidationErrors(['email', 'roles', 'permissions']);

    $user->refresh();

    expect($user->name)->toBe('Original Name')
        ->and($user->email)->toBe('original@example.test')
        ->and($user->hasRole('admin'))->toBeFalse();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'security',
        'event' => 'security.profile_mutation.denied',
        'causer_id' => $user->id,
        'causer_type' => User::class,
    ]);
});

it('updates the authenticated user password through the api when the current password is valid', function (): void {
    $user = User::factory()->create([
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->patchJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'a-stronger-password',
            'password_confirmation' => 'a-stronger-password',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Password updated successfully.')
        ->assertJsonPath('data', [])
        ->assertJsonPath('errors', []);

    expect(Hash::check('a-stronger-password', $user->refresh()->password))->toBeTrue();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'account',
        'event' => 'account.password.changed',
        'causer_id' => $user->id,
        'causer_type' => User::class,
    ]);
});

it('rejects password changes when the current password is wrong', function (): void {
    $user = User::factory()->create([
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->patchJson('/api/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'a-stronger-password',
            'password_confirmation' => 'a-stronger-password',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Validation failed.')
        ->assertJsonPath('errors.current_password.0', 'The password is incorrect.');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

it('rejects forbidden fields on the password endpoint to protect the self-service boundary', function (): void {
    $user = User::factory()->create([
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->patchJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'a-stronger-password',
            'password_confirmation' => 'a-stronger-password',
            'roles' => ['admin'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['roles']);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'security',
        'event' => 'security.profile_mutation.denied',
        'causer_id' => $user->id,
        'causer_type' => User::class,
    ]);
});
