<?php

use App\Models\ClientSecret;
use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
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
        'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
        'is_first_party' => false,
        'consent_bypass_allowed' => false,
    ]);

    $client->redirectUris()->create([
        'uri' => 'https://portal.example.com/callback',
        'is_primary' => true,
    ]);
    $client->scopes()->sync(Scope::query()->whereIn('code', ['openid', 'profile'])->pluck('id')->all());

    $user = clientManager(['clients.viewAny']);

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
            ->where('rows.0.trustTier', SsoClient::TRUST_TIER_THIRD_PARTY)
            ->where('rows.0.isFirstParty', false)
            ->where('rows.0.consentBypassAllowed', false)
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
    $user = clientManager(['clients.create']);

    $this->actingAs($user)
        ->get(route('admin.sso-clients.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Create')
            ->has('scopeOptions')
            ->has('trustTierOptions', 4)
            ->where('defaults.trustTier', SsoClient::TRUST_TIER_THIRD_PARTY)
            ->where('defaults.isFirstParty', false)
            ->where('defaults.consentBypassAllowed', false));
});

it('newly inserted client records receive safe trust defaults', function () {
    $clientId = DB::table('sso_clients')->insertGetId([
        'name' => 'Legacy Client',
        'client_id' => 'legacy_client',
        'client_secret_hash' => Hash::make('legacy-secret'),
        'redirect_uris' => json_encode(['https://legacy.example.com/callback'], JSON_THROW_ON_ERROR),
        'is_active' => true,
        'scopes' => json_encode(['openid'], JSON_THROW_ON_ERROR),
        'token_policy_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $client = SsoClient::query()->findOrFail($clientId);

    expect($client->trust_tier)->toBe(SsoClient::TRUST_TIER_THIRD_PARTY)
        ->and($client->is_first_party)->toBeFalse()
        ->and($client->consent_bypass_allowed)->toBeFalse();
});

it('authorized user can store client and receives secret once', function () {
    $user = clientManager(['clients.create']);

    $response = $this->actingAs($user)
        ->post(route('admin.sso-clients.store'), [
            'name' => 'Portal Client',
            'redirect_uris' => ['https://portal.example.com/callback'],
            'scopes' => ['openid', 'profile'],
            'is_active' => true,
            'token_policy_id' => null,
            'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED,
            'is_first_party' => true,
            'consent_bypass_allowed' => false,
        ]);

    $response
        ->assertRedirect(route('admin.sso-clients.index'))
        ->assertSessionHas('success')
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
    expect($client->trust_tier)->toBe(SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED);
    expect($client->is_first_party)->toBeTrue();
    expect($client->consent_bypass_allowed)->toBeFalse();
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
        'log_name' => 'admin.client',
        'event' => 'admin.client_secret.created',
    ]);

    $secretActivity = Activity::query()
        ->where('event', 'admin.client_secret.created')
        ->latest()
        ->firstOrFail();

    expect($secretActivity->properties->toArray())->toHaveKey('secret_last_four')
        ->and($secretActivity->properties->toArray())->not->toHaveKeys(['secret', 'client_secret']);
});

it('store validation fails for invalid client payload', function () {
    $user = clientManager(['clients.create']);

    $this->actingAs($user)
        ->from(route('admin.sso-clients.create'))
        ->post(route('admin.sso-clients.store'), [
            'name' => '',
            'redirect_uris' => ['not-a-url'],
            'scopes' => ['invalid-scope'],
            'is_active' => 'wrong',
            'trust_tier' => 'unknown-tier',
            'is_first_party' => 'wrong',
            'consent_bypass_allowed' => 'wrong',
        ])
        ->assertRedirect(route('admin.sso-clients.create'))
        ->assertSessionHasErrors(['name', 'redirect_uris.0', 'scopes.0', 'is_active', 'trust_tier', 'is_first_party', 'consent_bypass_allowed']);
});

it('authorized user can view client edit page without leaking secret', function () {
    $client = SsoClient::factory()->create([
        'name' => 'Portal Client',
        'client_id' => 'client_portal',
        'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED,
        'is_first_party' => true,
        'consent_bypass_allowed' => false,
    ]);
    $client->secrets()->create([
        'name' => 'Initial secret',
        'secret_hash' => Hash::make('very-secret-value'),
        'last_four' => '1234',
        'is_active' => true,
    ]);

    $user = clientManager(['clients.update', 'clients.manageSecrets']);

    $this->actingAs($user)
        ->get(route('admin.sso-clients.edit', $client))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Edit')
            ->where('client.id', $client->id)
            ->where('client.name', 'Portal Client')
            ->where('client.clientId', 'client_portal')
            ->where('client.trustTier', SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED)
            ->where('client.isFirstParty', true)
            ->where('client.consentBypassAllowed', false)
            ->where('client.secrets.0.lastFour', '1234')
            ->where('canManageSecrets', true)
            ->missing('client.clientSecret')
            ->missing('client.client_secret')
            ->has('trustTierOptions', 4));
});

it('authorized user can update client', function () {
    $client = SsoClient::factory()->create([
        'name' => 'Portal Client',
        'redirect_uris' => ['https://portal.example.com/callback'],
        'scopes' => ['openid'],
        'is_active' => true,
        'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
        'is_first_party' => false,
        'consent_bypass_allowed' => false,
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
    $user = clientManager(['clients.update']);

    $this->actingAs($user)
        ->put(route('admin.sso-clients.update', $client), [
            'name' => 'Portal Client Updated',
            'redirect_uris' => ['https://portal.example.com/auth/callback'],
            'scopes' => ['openid', 'email'],
            'is_active' => false,
            'token_policy_id' => null,
            'trust_tier' => SsoClient::TRUST_TIER_MACHINE_TO_MACHINE,
            'is_first_party' => false,
            'consent_bypass_allowed' => true,
        ])
        ->assertRedirect(route('admin.sso-clients.index'))
        ->assertSessionHas('success');

    $client->refresh()->load(['redirectUris', 'scopes']);

    expect($client->name)->toBe('Portal Client Updated');
    expect($client->normalizedRedirectUris())->toBe(['https://portal.example.com/auth/callback']);
    expect($client->normalizedScopeCodes())->toBe(['email', 'openid']);
    expect($client->is_active)->toBeFalse();
    expect($client->trust_tier)->toBe(SsoClient::TRUST_TIER_MACHINE_TO_MACHINE);
    expect($client->is_first_party)->toBeFalse();
    expect($client->consent_bypass_allowed)->toBeTrue();
    expect($client->client_secret_hash)->toBe($originalSecretHash);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'admin.client',
        'event' => 'admin.client.updated',
    ]);
});

it('update validation fails for invalid client payload', function () {
    $client = SsoClient::factory()->create();
    $user = clientManager(['clients.update']);

    $this->actingAs($user)
        ->from(route('admin.sso-clients.edit', $client))
        ->put(route('admin.sso-clients.update', $client), [
            'name' => '',
            'redirect_uris' => [],
            'scopes' => ['unknown'],
            'is_active' => 'wrong',
            'trust_tier' => 'invalid-tier',
            'is_first_party' => 'wrong',
            'consent_bypass_allowed' => 'wrong',
        ])
        ->assertRedirect(route('admin.sso-clients.edit', $client))
        ->assertSessionHasErrors(['name', 'redirect_uris', 'scopes.0', 'is_active', 'trust_tier', 'is_first_party', 'consent_bypass_allowed']);
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
        ->assertSessionHas('success')
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
        'log_name' => 'admin.client',
        'event' => 'admin.client_secret.rotated',
    ]);

    $rotationActivity = Activity::query()
        ->where('event', 'admin.client_secret.rotated')
        ->latest()
        ->firstOrFail();

    expect($rotationActivity->properties->toArray())->toHaveKey('secret_last_four')
        ->and($rotationActivity->properties->toArray())->not->toHaveKeys(['secret', 'client_secret']);
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
        ->assertSessionHas('success');

    expect($secretToRevoke->fresh()->revoked_at)->not->toBeNull();
    expect($secretToRevoke->fresh()->is_active)->toBeFalse();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'admin.client',
        'event' => 'admin.client_secret.revoked',
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
    $user = clientManager(['clients.delete']);

    $this->actingAs($user)
        ->delete(route('admin.sso-clients.destroy', $client))
        ->assertRedirect(route('admin.sso-clients.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('sso_clients', [
        'id' => $client->id,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'admin.client',
        'event' => 'admin.client.deleted',
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
