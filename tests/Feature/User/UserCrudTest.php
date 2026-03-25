<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function userManager(array $abilities = []): User
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

it('authorized user can view user index with modal-ready props', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $listedUser = User::factory()->create([
        'name' => 'Taylor Otwell',
        'email' => 'taylor@example.com',
    ]);
    $listedUser->assignRole('admin');

    $manager = userManager(['users.view']);

    $this->actingAs($manager)
        ->get(route('admin.users.index', ['global' => 'Taylor']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Index')
            ->has('rows', 1)
            ->where('rows.0.name', 'Taylor Otwell')
            ->where('rows.0.email', 'taylor@example.com')
            ->where('rows.0.roles.0', 'admin')
            ->where('filters.global', 'Taylor')
            ->has('roleOptions', 1)
            ->where('roleOptions.0.value', 'admin')
            ->where('canManageUsers', false));
});

it('unauthorized user is forbidden from user index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('authorized user can store user with roles', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $manager = userManager(['users.manage']);

    $this->actingAs($manager)
        ->post(route('admin.users.store'), [
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'roles' => ['admin'],
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'User created successfully.');

    $createdUser = User::where('email', 'new-admin@example.com')->firstOrFail();

    expect($createdUser->hasRole('admin'))->toBeTrue();
    expect(Hash::check('password123', $createdUser->password))->toBeTrue();
});

it('store validation fails for invalid user payload', function () {
    $manager = userManager(['users.manage']);

    $this->actingAs($manager)
        ->post(route('admin.users.store'), [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
        ->assertSessionHasErrors(['name', 'email', 'password']);
});

it('forbids user store when unauthorized', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.users.store'), [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertForbidden();
});

it('authorized user can update user and sync roles', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'reviewer', 'guard_name' => 'web']);
    $targetUser = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);
    $targetUser->assignRole('admin');
    $manager = userManager(['users.manage']);

    $this->actingAs($manager)
        ->put(route('admin.users.update', $targetUser), [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'roles' => ['reviewer'],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'User updated successfully.');

    $targetUser->refresh();

    expect($targetUser->name)->toBe('Updated Name');
    expect($targetUser->email)->toBe('updated@example.com');
    expect($targetUser->hasRole('reviewer'))->toBeTrue();
    expect($targetUser->hasRole('admin'))->toBeFalse();
});

it('update validation fails for invalid user payload', function () {
    $targetUser = User::factory()->create([
        'email' => 'target@example.com',
    ]);
    User::factory()->create([
        'email' => 'taken@example.com',
    ]);
    $manager = userManager(['users.manage']);

    $this->actingAs($manager)
        ->put(route('admin.users.update', $targetUser), [
            'name' => '',
            'email' => 'taken@example.com',
            'roles' => [],
        ])
        ->assertSessionHasErrors(['name', 'email']);
});

it('forbids user update when unauthorized', function () {
    $targetUser = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('admin.users.update', $targetUser), [
            'name' => 'Blocked Update',
            'email' => 'blocked-update@example.com',
            'roles' => [],
        ])
        ->assertForbidden();
});

it('authorized user can delete a regular user', function () {
    $targetUser = User::factory()->create();
    $manager = userManager(['users.manage']);

    $this->actingAs($manager)
        ->deleteJson(route('admin.users.destroy', $targetUser))
        ->assertOk()
        ->assertJson([
            'message' => 'User deleted successfully.',
            'data' => [
                'id' => $targetUser->id,
            ],
        ]);

    $this->assertDatabaseMissing('users', [
        'id' => $targetUser->id,
    ]);
});

it('forbids deleting the currently signed-in user', function () {
    $manager = userManager(['users.manage']);

    $this->actingAs($manager)
        ->deleteJson(route('admin.users.destroy', $manager))
        ->assertForbidden();
});

it('blocks deleting a protected system user', function () {
    $protectedUser = User::factory()->create([
        'email' => 'admin@sso.test',
    ]);
    $manager = userManager(['users.manage']);

    $this->actingAs($manager)
        ->deleteJson(route('admin.users.destroy', $protectedUser))
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'This protected system user cannot be deleted.',
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $protectedUser->id,
    ]);
});

it('authorized user can bulk delete regular users', function () {
    $targets = User::factory()->count(2)->create();
    $manager = userManager(['users.manage']);

    $this->actingAs($manager)
        ->deleteJson(route('admin.users.bulk-destroy'), [
            'ids' => $targets->pluck('id')->all(),
        ])
        ->assertOk()
        ->assertJson([
            'message' => 'Selected users deleted successfully.',
            'meta' => [
                'deletedCount' => 2,
            ],
        ]);

    foreach ($targets as $target) {
        $this->assertDatabaseMissing('users', [
            'id' => $target->id,
        ]);
    }
});

it('blocks bulk delete when the current user is included', function () {
    $manager = userManager(['users.manage']);
    $otherUser = User::factory()->create();

    $this->actingAs($manager)
        ->deleteJson(route('admin.users.bulk-destroy'), [
            'ids' => [$manager->id, $otherUser->id],
        ])
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'The currently signed-in user cannot be deleted.',
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $manager->id,
    ]);
    $this->assertDatabaseHas('users', [
        'id' => $otherUser->id,
    ]);
});

it('forbids user bulk delete when unauthorized', function () {
    $targets = User::factory()->count(2)->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('admin.users.bulk-destroy'), [
            'ids' => $targets->pluck('id')->all(),
        ])
        ->assertForbidden();
});
