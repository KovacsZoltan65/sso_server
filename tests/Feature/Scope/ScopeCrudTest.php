<?php

use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutVite();
});

function scopeManager(array $abilities = []): User
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

it('authorized user can view scope index', function () {
    Scope::factory()->create([
        'name' => 'Users Read',
        'code' => 'users.read',
        'description' => 'Read the user directory.',
        'is_active' => true,
    ]);

    $user = scopeManager(['scopes.viewAny']);

    $this->actingAs($user)
        ->get(route('admin.scopes.index', ['global' => 'users']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Scopes/Index')
            ->has('rows', 1)
            ->where('rows.0.name', 'Users Read')
            ->where('rows.0.code', 'users.read')
            ->where('rows.0.isActive', true)
            ->where('filters.global', 'users')
            ->where('canManageScopes', false));
});

it('unauthorized user is forbidden from scope index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.scopes.index'))
        ->assertForbidden();
});

it('authorized user can view scope create page', function () {
    $user = scopeManager(['scopes.create']);

    $this->actingAs($user)
        ->get(route('admin.scopes.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Scopes/Create'));
});

it('authorized user can store scope', function () {
    $user = scopeManager(['scopes.create']);

    $this->actingAs($user)
        ->post(route('admin.scopes.store'), [
            'name' => 'Profile Read',
            'code' => 'profile.read',
            'description' => 'Read profile claims.',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.scopes.index'))
        ->assertSessionHas('success', 'Scope created successfully.');

    $this->assertDatabaseHas('scopes', [
        'name' => 'Profile Read',
        'code' => 'profile.read',
        'is_active' => true,
    ]);
});

it('store validation fails for invalid scope payload', function () {
    Scope::factory()->create(['code' => 'profile.read']);
    $user = scopeManager(['scopes.create']);

    $this->actingAs($user)
        ->from(route('admin.scopes.create'))
        ->post(route('admin.scopes.store'), [
            'name' => '',
            'code' => 'INVALID CODE',
            'description' => str_repeat('a', 2001),
            'is_active' => 'wrong',
        ])
        ->assertRedirect(route('admin.scopes.create'))
        ->assertSessionHasErrors(['name', 'code', 'description', 'is_active']);
});

it('authorized user can view scope edit page', function () {
    $scope = Scope::factory()->create([
        'name' => 'Clients Manage',
        'code' => 'clients.manage',
    ]);
    $user = scopeManager(['scopes.update']);

    $this->actingAs($user)
        ->get(route('admin.scopes.edit', $scope))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Scopes/Edit')
            ->where('scope.id', $scope->id)
            ->where('scope.code', 'clients.manage'));
});

it('authorized user can update scope', function () {
    $scope = Scope::factory()->create([
        'name' => 'Clients Manage',
        'code' => 'clients.manage',
        'is_active' => true,
    ]);
    $user = scopeManager(['scopes.update']);

    $this->actingAs($user)
        ->put(route('admin.scopes.update', $scope), [
            'name' => 'Clients Operate',
            'code' => 'clients.operate',
            'description' => 'Operate client records.',
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.scopes.index'))
        ->assertSessionHas('success', 'Scope updated successfully.');

    $scope->refresh();

    expect($scope->name)->toBe('Clients Operate');
    expect($scope->code)->toBe('clients.operate');
    expect($scope->is_active)->toBeFalse();
});

it('prevents changing scope code while assigned to a client', function () {
    $scope = Scope::factory()->create([
        'name' => 'Profile',
        'code' => 'profile',
    ]);
    SsoClient::factory()->create([
        'scopes' => ['profile'],
    ]);
    $user = scopeManager(['scopes.update']);

    $this->actingAs($user)
        ->from(route('admin.scopes.edit', $scope))
        ->put(route('admin.scopes.update', $scope), [
            'name' => 'Profile',
            'code' => 'profile.read',
            'description' => 'Read profile claims.',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.scopes.edit', $scope))
        ->assertSessionHasErrors(['code']);
});

it('authorized user can delete unused scope', function () {
    $scope = Scope::factory()->create();
    $user = scopeManager(['scopes.delete']);

    $this->actingAs($user)
        ->delete(route('admin.scopes.destroy', $scope))
        ->assertRedirect(route('admin.scopes.index'))
        ->assertSessionHas('success', 'Scope deleted successfully.');

    $this->assertDatabaseMissing('scopes', [
        'id' => $scope->id,
    ]);
});

it('prevents deleting scope assigned to clients', function () {
    $scope = Scope::factory()->create([
        'code' => 'profile',
    ]);
    SsoClient::factory()->create([
        'scopes' => ['profile'],
    ]);
    $user = scopeManager(['scopes.delete']);

    $this->actingAs($user)
        ->deleteJson(route('admin.scopes.destroy', $scope))
        ->assertStatus(422)
        ->assertJsonPath('message', 'This scope is assigned to clients and cannot be deleted.');
});

it('authorized user can bulk delete unused scopes', function () {
    $scopes = Scope::factory()->count(2)->create();
    $user = scopeManager(['scopes.deleteAny']);

    $this->actingAs($user)
        ->deleteJson(route('admin.scopes.bulk-destroy'), [
            'ids' => $scopes->pluck('id')->all(),
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Selected scopes deleted successfully.')
        ->assertJsonPath('meta.deletedCount', 2);

    $this->assertDatabaseCount('scopes', 0);
});

it('prevents bulk delete for scopes assigned to clients', function () {
    $inUseScope = Scope::factory()->create(['code' => 'email']);
    $unusedScope = Scope::factory()->create(['code' => 'tokens.issue']);
    SsoClient::factory()->create([
        'scopes' => ['email'],
    ]);
    $user = scopeManager(['scopes.deleteAny']);

    $this->actingAs($user)
        ->deleteJson(route('admin.scopes.bulk-destroy'), [
            'ids' => [$inUseScope->id, $unusedScope->id],
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This scope is assigned to clients and cannot be deleted.');
});
