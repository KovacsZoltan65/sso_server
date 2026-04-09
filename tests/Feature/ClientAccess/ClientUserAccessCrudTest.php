<?php

use App\Models\ClientUserAccess;
use App\Models\SsoClient;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutVite();
});

function clientAccessManager(array $abilities = []): User
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

it('loads the client access admin index with expected props', function () {
    $client = SsoClient::factory()->create([
        'name' => 'Portal',
        'client_id' => 'client_portal',
    ]);
    $targetUser = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);
    ClientUserAccess::factory()->create([
        'client_id' => $client->id,
        'user_id' => $targetUser->id,
        'allowed_from' => now()->subHour(),
        'allowed_until' => now()->addHour(),
    ]);

    $manager = clientAccessManager(['client-access.viewAny']);

    $this->actingAs($manager)
        ->get(route('admin.client-user-access.index', ['global' => 'Portal']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ClientUserAccess/Index')
            ->has('rows', 1)
            ->where('rows.0.clientName', 'Portal')
            ->where('rows.0.clientPublicId', 'client_portal')
            ->where('rows.0.userName', 'Jane Doe')
            ->where('rows.0.userEmail', 'jane@example.com')
            ->where('filters.global', 'Portal')
            ->has('clientOptions')
            ->has('userOptions')
            ->where('canManageClientAccess', false));
});

it('loads the client access create page with form options', function () {
    SsoClient::factory()->create([
        'name' => 'Portal',
        'client_id' => 'client_portal',
    ]);
    User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);
    $manager = clientAccessManager(['client-access.create']);

    $this->actingAs($manager)
        ->get(route('admin.client-user-access.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ClientUserAccess/Create')
            ->has('clientOptions')
            ->has('userOptions'));
});

it('loads the client access edit page with the selected access payload', function () {
    $access = ClientUserAccess::factory()->create();
    $manager = clientAccessManager(['client-access.update']);

    $this->actingAs($manager)
        ->get(route('admin.client-user-access.edit', $access))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ClientUserAccess/Edit')
            ->where('access.id', $access->id)
            ->where('access.clientId', $access->client_id)
            ->where('access.userId', $access->user_id)
            ->has('clientOptions')
            ->has('userOptions'));
});

it('stores a client user access record through the admin page flow', function () {
    $client = SsoClient::factory()->create();
    $targetUser = User::factory()->create();
    $manager = clientAccessManager(['client-access.create']);

    $this->actingAs($manager)
        ->post(route('admin.client-user-access.store'), [
            'client_id' => $client->id,
            'user_id' => $targetUser->id,
            'is_active' => true,
            'allowed_from' => now()->subHour()->toDateTimeString(),
            'allowed_until' => now()->addHour()->toDateTimeString(),
            'notes' => 'Temporary rollout access',
        ])
        ->assertRedirect(route('admin.client-user-access.index'))
        ->assertSessionHas('success', 'Client user access created successfully.');

    $this->assertDatabaseHas('client_user_access', [
        'client_id' => $client->id,
        'user_id' => $targetUser->id,
        'is_active' => true,
    ]);
});

it('updates a client user access record through the admin page flow', function () {
    $client = SsoClient::factory()->create();
    $targetUser = User::factory()->create();
    $access = ClientUserAccess::factory()->create([
        'client_id' => $client->id,
        'user_id' => $targetUser->id,
        'is_active' => true,
    ]);
    $manager = clientAccessManager(['client-access.update']);

    $this->actingAs($manager)
        ->put(route('admin.client-user-access.update', $access), [
            'client_id' => $client->id,
            'user_id' => $targetUser->id,
            'is_active' => false,
            'allowed_from' => now()->subDays(2)->toDateTimeString(),
            'allowed_until' => now()->addDays(2)->toDateTimeString(),
            'notes' => 'Disabled after audit review',
        ])
        ->assertRedirect(route('admin.client-user-access.index'))
        ->assertSessionHas('success', 'Client user access updated successfully.');

    $access->refresh();

    expect($access->is_active)->toBeFalse()
        ->and($access->notes)->toBe('Disabled after audit review');
});

it('stores a client user access record and audits it', function () {
    $client = SsoClient::factory()->create();
    $targetUser = User::factory()->create();
    $manager = clientAccessManager(['client-access.create']);

    $this->actingAs($manager)
        ->postJson(route('api.client-user-access.store'), [
            'client_id' => $client->id,
            'user_id' => $targetUser->id,
            'is_active' => true,
            'allowed_from' => now()->subHour()->toDateTimeString(),
            'allowed_until' => now()->addHour()->toDateTimeString(),
            'notes' => 'Temporary rollout access',
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Client user access created successfully.')
        ->assertJsonPath('data.access.clientId', $client->id)
        ->assertJsonPath('data.access.userId', $targetUser->id);

    $this->assertDatabaseHas('client_user_access', [
        'client_id' => $client->id,
        'user_id' => $targetUser->id,
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'admin.client_access',
        'event' => 'admin.client_user_access.created',
        'description' => 'Client user access created.',
        'causer_id' => $manager->id,
    ]);
});

it('updates a client user access record and audits it', function () {
    $client = SsoClient::factory()->create();
    $targetUser = User::factory()->create();
    $access = ClientUserAccess::factory()->create([
        'client_id' => $client->id,
        'user_id' => $targetUser->id,
        'is_active' => true,
    ]);
    $manager = clientAccessManager(['client-access.update']);

    $this->actingAs($manager)
        ->putJson(route('api.client-user-access.update', $access), [
            'client_id' => $client->id,
            'user_id' => $targetUser->id,
            'is_active' => false,
            'allowed_from' => now()->subDays(2)->toDateTimeString(),
            'allowed_until' => now()->addDays(2)->toDateTimeString(),
            'notes' => 'Disabled after audit review',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Client user access updated successfully.');

    $access->refresh();

    expect($access->is_active)->toBeFalse()
        ->and($access->notes)->toBe('Disabled after audit review');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'admin.client_access',
        'event' => 'admin.client_user_access.updated',
        'description' => 'Client user access updated.',
        'causer_id' => $manager->id,
    ]);
});

it('deletes a client user access record and audits it', function () {
    $access = ClientUserAccess::factory()->create();
    $manager = clientAccessManager(['client-access.delete']);

    $this->actingAs($manager)
        ->deleteJson(route('api.client-user-access.destroy', $access))
        ->assertOk()
        ->assertJsonPath('message', 'Client user access deleted successfully.');

    $this->assertDatabaseMissing('client_user_access', [
        'id' => $access->id,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'admin.client_access',
        'event' => 'admin.client_user_access.deleted',
        'description' => 'Client user access deleted.',
        'causer_id' => $manager->id,
    ]);
});

it('bulk deletes client user access records', function () {
    $accesses = ClientUserAccess::factory()->count(2)->create();
    $manager = clientAccessManager(['client-access.deleteAny']);

    $this->actingAs($manager)
        ->deleteJson(route('api.client-user-access.bulk-destroy'), [
            'ids' => $accesses->pluck('id')->all(),
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Selected client user access records deleted successfully.')
        ->assertJsonPath('meta.deletedCount', 2);

    foreach ($accesses as $access) {
        $this->assertDatabaseMissing('client_user_access', [
            'id' => $access->id,
        ]);
    }
});

it('forbids unauthorized management requests', function () {
    $client = SsoClient::factory()->create();
    $targetUser = User::factory()->create();
    $otherUser = User::factory()->create();
    $access = ClientUserAccess::factory()->create([
        'client_id' => $client->id,
        'user_id' => $targetUser->id,
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.client-user-access.store'), [
            'client_id' => $client->id,
            'user_id' => $otherUser->id,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->putJson(route('api.client-user-access.update', $access), [
            'client_id' => $client->id,
            'user_id' => $targetUser->id,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->deleteJson(route('api.client-user-access.destroy', $access))
        ->assertForbidden();
});

it('validates missing client, missing user, invalid date range, and duplicate assignments', function () {
    $client = SsoClient::factory()->create();
    $targetUser = User::factory()->create();
    ClientUserAccess::factory()->create([
        'client_id' => $client->id,
        'user_id' => $targetUser->id,
    ]);
    $manager = clientAccessManager(['client-access.create']);

    $this->actingAs($manager)
        ->postJson(route('api.client-user-access.store'), [
            'user_id' => $targetUser->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['client_id']);

    $this->actingAs($manager)
        ->postJson(route('api.client-user-access.store'), [
            'client_id' => $client->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);

    $this->actingAs($manager)
        ->postJson(route('api.client-user-access.store'), [
            'client_id' => $client->id,
            'user_id' => User::factory()->create()->id,
            'allowed_from' => now()->addDay()->toDateTimeString(),
            'allowed_until' => now()->toDateTimeString(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['allowed_until']);

    $duplicateResponse = $this->actingAs($manager)
        ->postJson(route('api.client-user-access.store'), [
            'client_id' => $client->id,
            'user_id' => $targetUser->id,
        ]);

    $duplicateResponse
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);
});

it('exposes model relations for client access records', function () {
    $access = ClientUserAccess::factory()->create();

    expect($access->client)->toBeInstanceOf(SsoClient::class)
        ->and($access->user)->toBeInstanceOf(User::class)
        ->and($access->client->userAccesses->contains('id', $access->id))->toBeTrue()
        ->and($access->user->clientAccesses->contains('id', $access->id))->toBeTrue();
});

it('returns client and user scoped access listings', function () {
    $access = ClientUserAccess::factory()->create();
    $manager = clientAccessManager(['client-access.viewAny']);

    $this->actingAs($manager)
        ->getJson(route('api.sso-clients.user-accesses', $access->client))
        ->assertOk()
        ->assertJsonPath('data.rows.0.user_id', $access->user_id);

    $this->actingAs($manager)
        ->getJson(route('api.users.client-accesses', $access->user))
        ->assertOk()
        ->assertJsonPath('data.rows.0.client_id', $access->client_id);
});
