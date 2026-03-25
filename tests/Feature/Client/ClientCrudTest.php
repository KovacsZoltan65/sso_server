<?php

use App\Models\SsoClient;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutVite();
});

function clientManager(array $abilities = []): User
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

it('authorized user can view client index', function () {
    SsoClient::factory()->create([
        'name' => 'Portal Client',
        'client_id' => 'client_portal',
        'redirect_uris' => ['https://portal.example.com/callback'],
        'scopes' => ['openid', 'profile'],
    ]);

    $user = clientManager(['sso-clients.view']);

    $this->actingAs($user)
        ->get(route('admin.sso-clients.index', ['global' => 'Portal']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Index')
            ->has('rows', 1)
            ->where('rows.0.name', 'Portal Client')
            ->where('rows.0.clientId', 'client_portal')
            ->where('rows.0.redirectUriCount', 1)
            ->where('rows.0.scopesCount', 2)
            ->where('filters.global', 'Portal')
            ->where('canManageClients', false)
            ->has('scopeOptions'));
});

it('unauthorized user is forbidden from client index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.sso-clients.index'))
        ->assertForbidden();
});

it('authorized user can view client create page', function () {
    $user = clientManager(['sso-clients.manage']);

    $this->actingAs($user)
        ->get(route('admin.sso-clients.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Create')
            ->has('scopeOptions'));
});

it('authorized user can store client and receives secret once', function () {
    $user = clientManager(['sso-clients.manage']);

    $response = $this->actingAs($user)
        ->post(route('admin.sso-clients.store'), [
            'name' => 'Portal Client',
            'redirect_uris' => ['https://portal.example.com/callback'],
            'scopes' => ['openid', 'profile'],
            'is_active' => true,
            'token_policy_id' => null,
        ]);

    $response
        ->assertRedirect(route('admin.sso-clients.index'))
        ->assertSessionHas('success', 'SSO client created successfully.')
        ->assertSessionHas('clientSecret');

    $client = SsoClient::query()->firstOrFail();
    $clientSecret = $response->baseResponse->getSession()->get('clientSecret');

    expect($client->name)->toBe('Portal Client');
    expect($client->client_id)->not->toBe('');
    expect($client->client_secret_hash)->not->toBe('');
    expect(Hash::check($clientSecret['secret'], $client->client_secret_hash))->toBeTrue();
});

it('store validation fails for invalid client payload', function () {
    $user = clientManager(['sso-clients.manage']);

    $this->actingAs($user)
        ->from(route('admin.sso-clients.create'))
        ->post(route('admin.sso-clients.store'), [
            'name' => '',
            'redirect_uris' => ['not-a-url'],
            'scopes' => ['invalid-scope'],
            'is_active' => 'wrong',
        ])
        ->assertRedirect(route('admin.sso-clients.create'))
        ->assertSessionHasErrors(['name', 'redirect_uris.0', 'scopes.0', 'is_active']);
});

it('authorized user can view client edit page without leaking secret', function () {
    $client = SsoClient::factory()->create([
        'name' => 'Portal Client',
        'client_id' => 'client_portal',
    ]);
    $user = clientManager(['sso-clients.manage']);

    $this->actingAs($user)
        ->get(route('admin.sso-clients.edit', $client))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Edit')
            ->where('client.id', $client->id)
            ->where('client.name', 'Portal Client')
            ->where('client.clientId', 'client_portal')
            ->missing('client.clientSecret')
            ->missing('client.client_secret'));
});

it('authorized user can update client', function () {
    $client = SsoClient::factory()->create([
        'name' => 'Portal Client',
        'redirect_uris' => ['https://portal.example.com/callback'],
        'scopes' => ['openid'],
        'is_active' => true,
    ]);
    $originalSecretHash = $client->client_secret_hash;
    $user = clientManager(['sso-clients.manage']);

    $this->actingAs($user)
        ->put(route('admin.sso-clients.update', $client), [
            'name' => 'Portal Client Updated',
            'redirect_uris' => ['https://portal.example.com/auth/callback'],
            'scopes' => ['openid', 'email'],
            'is_active' => false,
            'token_policy_id' => null,
        ])
        ->assertRedirect(route('admin.sso-clients.index'))
        ->assertSessionHas('success', 'SSO client updated successfully.');

    $client->refresh();

    expect($client->name)->toBe('Portal Client Updated');
    expect($client->redirect_uris)->toBe(['https://portal.example.com/auth/callback']);
    expect($client->scopes)->toBe(['openid', 'email']);
    expect($client->is_active)->toBeFalse();
    expect($client->client_secret_hash)->toBe($originalSecretHash);
});

it('update validation fails for invalid client payload', function () {
    $client = SsoClient::factory()->create();
    $user = clientManager(['sso-clients.manage']);

    $this->actingAs($user)
        ->from(route('admin.sso-clients.edit', $client))
        ->put(route('admin.sso-clients.update', $client), [
            'name' => '',
            'redirect_uris' => [],
            'scopes' => ['unknown'],
            'is_active' => 'wrong',
        ])
        ->assertRedirect(route('admin.sso-clients.edit', $client))
        ->assertSessionHasErrors(['name', 'redirect_uris', 'scopes.0', 'is_active']);
});

it('authorized user can delete client', function () {
    $client = SsoClient::factory()->create();
    $user = clientManager(['sso-clients.manage']);

    $this->actingAs($user)
        ->delete(route('admin.sso-clients.destroy', $client))
        ->assertRedirect(route('admin.sso-clients.index'))
        ->assertSessionHas('success', 'SSO client deleted successfully.');

    $this->assertDatabaseMissing('sso_clients', [
        'id' => $client->id,
    ]);
});

it('forbids client delete when unauthorized', function () {
    $client = SsoClient::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('admin.sso-clients.destroy', $client))
        ->assertForbidden();
});
