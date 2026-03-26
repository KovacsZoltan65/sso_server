<?php

use App\Models\ClientSecret;
use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutVite();

    Scope::factory()->create([
        'name' => 'OpenID',
        'code' => 'openid',
        'is_active' => true,
    ]);

    Scope::factory()->create([
        'name' => 'Profile',
        'code' => 'profile',
        'is_active' => true,
    ]);

    Scope::factory()->create([
        'name' => 'Email',
        'code' => 'email',
        'is_active' => true,
    ]);
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
    $client = SsoClient::factory()->create([
        'name' => 'Portal Client',
        'client_id' => 'client_portal',
        'redirect_uris' => ['https://portal.example.com/callback'],
        'scopes' => ['openid', 'profile'],
    ]);

    $client->redirectUris()->create([
        'uri' => 'https://portal.example.com/callback',
        'is_primary' => true,
    ]);
    $client->scopes()->sync(Scope::query()->whereIn('code', ['openid', 'profile'])->pluck('id')->all());

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

    $client = SsoClient::query()->with(['redirectUris', 'scopes', 'secrets'])->firstOrFail();
    $clientSecret = $response->baseResponse->getSession()->get('clientSecret');
    $storedSecret = $client->secrets->first();

    expect($client->name)->toBe('Portal Client');
    expect($client->client_id)->not->toBe('');
    expect($client->client_secret_hash)->not->toBe('');
    expect(Hash::check($clientSecret['secret'], $client->client_secret_hash))->toBeTrue();
    expect($client->normalizedRedirectUris())->toBe(['https://portal.example.com/callback']);
    expect($client->normalizedScopeCodes())->toBe(['openid', 'profile']);
    expect($storedSecret)->not->toBeNull();
    expect(Hash::check($clientSecret['secret'], $storedSecret->secret_hash))->toBeTrue();

    $this->assertDatabaseHas('redirect_uris', [
        'sso_client_id' => $client->id,
        'uri' => 'https://portal.example.com/callback',
    ]);

    $this->assertDatabaseHas('client_scopes', [
        'sso_client_id' => $client->id,
        'scope_id' => Scope::query()->where('code', 'openid')->value('id'),
    ]);

    $this->assertDatabaseHas('activity_log', [
        'event' => 'client.secret.created',
    ]);
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
    $client->secrets()->create([
        'name' => 'Initial secret',
        'secret_hash' => Hash::make('very-secret-value'),
        'last_four' => '1234',
        'is_active' => true,
    ]);

    $user = clientManager(['sso-clients.manage', 'clients.manageSecrets']);

    $this->actingAs($user)
        ->get(route('admin.sso-clients.edit', $client))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Edit')
            ->where('client.id', $client->id)
            ->where('client.name', 'Portal Client')
            ->where('client.clientId', 'client_portal')
            ->where('client.secrets.0.lastFour', '1234')
            ->where('canManageSecrets', true)
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
    $client->redirectUris()->create([
        'uri' => 'https://portal.example.com/callback',
        'is_primary' => true,
    ]);
    $client->scopes()->sync(Scope::query()->where('code', 'openid')->pluck('id')->all());
    $client->secrets()->create([
        'name' => 'Initial secret',
        'secret_hash' => $client->client_secret_hash,
        'last_four' => '9999',
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

    $client->refresh()->load(['redirectUris', 'scopes']);

    expect($client->name)->toBe('Portal Client Updated');
    expect($client->normalizedRedirectUris())->toBe(['https://portal.example.com/auth/callback']);
    expect($client->normalizedScopeCodes())->toBe(['email', 'openid']);
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

it('authorized user can rotate client secret and old one becomes revoked', function () {
    $client = SsoClient::factory()->create([
        'name' => 'Portal Client',
        'client_id' => 'client_portal',
    ]);
    $oldSecret = $client->secrets()->create([
        'name' => 'Initial secret',
        'secret_hash' => Hash::make('old-secret-value'),
        'last_four' => '1111',
        'is_active' => true,
    ]);

    $user = clientManager(['clients.rotateSecret']);

    $response = $this->actingAs($user)
        ->post(route('admin.sso-clients.rotate-secret', $client), [
            'name' => 'Rotation before deploy',
        ]);

    $response
        ->assertRedirect(route('admin.sso-clients.edit', $client))
        ->assertSessionHas('success', 'Client secret rotated successfully.')
        ->assertSessionHas('clientSecret');

    $client->refresh()->load('secrets');
    $sessionSecret = $response->baseResponse->getSession()->get('clientSecret')['secret'];

    expect($client->secrets)->toHaveCount(2);
    expect($client->fresh()->activeSecrets()->count())->toBe(1);
    expect($oldSecret->fresh()->revoked_at)->not->toBeNull();
    expect($oldSecret->fresh()->is_active)->toBeFalse();

    $newSecret = $client->secrets()->where('name', 'Rotation before deploy')->firstOrFail();
    expect(Hash::check($sessionSecret, $newSecret->secret_hash))->toBeTrue();
    expect(Hash::check($sessionSecret, $client->fresh()->client_secret_hash))->toBeTrue();

    $this->assertDatabaseHas('activity_log', [
        'event' => 'client.secret.rotated',
    ]);
});

it('authorized user can revoke a non-last secret', function () {
    $client = SsoClient::factory()->create();
    $client->secrets()->create([
        'name' => 'Older secret',
        'secret_hash' => Hash::make('older-secret'),
        'last_four' => '1111',
        'is_active' => true,
    ]);
    $secretToRevoke = $client->secrets()->create([
        'name' => 'Newest secret',
        'secret_hash' => Hash::make('newest-secret'),
        'last_four' => '2222',
        'is_active' => true,
    ]);

    $user = clientManager(['clients.manageSecrets']);

    $this->actingAs($user)
        ->delete(route('admin.sso-clients.revoke-secret', [$client, $secretToRevoke]))
        ->assertRedirect(route('admin.sso-clients.edit', $client))
        ->assertSessionHas('success', 'Client secret revoked successfully.');

    expect($secretToRevoke->fresh()->revoked_at)->not->toBeNull();
    expect($secretToRevoke->fresh()->is_active)->toBeFalse();

    $this->assertDatabaseHas('activity_log', [
        'event' => 'client.secret.revoked',
    ]);
});

it('cannot revoke the last active client secret', function () {
    $client = SsoClient::factory()->create();
    $secret = $client->secrets()->create([
        'name' => 'Only secret',
        'secret_hash' => Hash::make('only-secret'),
        'last_four' => '3333',
        'is_active' => true,
    ]);

    $user = clientManager(['clients.manageSecrets']);

    $this->actingAs($user)
        ->from(route('admin.sso-clients.edit', $client))
        ->delete(route('admin.sso-clients.revoke-secret', [$client, $secret]))
        ->assertRedirect()
        ->assertSessionHasErrors(['secret']);

    expect($secret->fresh()->revoked_at)->toBeNull();
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

it('forbids rotating or revoking client secrets when unauthorized', function () {
    $client = SsoClient::factory()->create();
    $secret = $client->secrets()->create([
        'name' => 'Only secret',
        'secret_hash' => Hash::make('plain-secret'),
        'last_four' => '5555',
        'is_active' => true,
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.sso-clients.rotate-secret', $client))
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('admin.sso-clients.revoke-secret', [$client, $secret]))
        ->assertForbidden();
});
