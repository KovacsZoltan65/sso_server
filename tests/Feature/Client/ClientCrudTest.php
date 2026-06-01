<?php

use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\Token;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Services\OAuth\OAuthAuthorizationService;
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

function prepareClientForSecretTokenExchange(SsoClient $client): SsoClient
{
    $policy = TokenPolicy::query()->create([
        'name' => 'Client Secret Token Exchange Policy',
        'code' => 'client.secret.exchange.'.fake()->unique()->numerify('###'),
        'description' => 'Policy for client secret hardening tests.',
        'access_token_ttl_minutes' => 60,
        'refresh_token_ttl_minutes' => 1440,
        'refresh_token_rotation_enabled' => true,
        'pkce_required' => true,
        'reuse_refresh_token_forbidden' => true,
        'is_default' => false,
        'is_active' => true,
    ]);

    $client->forceFill([
        'token_policy_id' => $policy->id,
        'is_active' => true,
        'client_type' => SsoClient::CLIENT_TYPE_CONFIDENTIAL,
    ])->save();

    $client->redirectUris()->updateOrCreate(
        ['uri_hash' => hash('sha256', 'https://portal.example.com/callback')],
        [
            'uri' => 'https://portal.example.com/callback',
            'is_primary' => true,
        ],
    );

    $client->scopes()->sync(
        Scope::query()
            ->whereIn('code', ['profile'])
            ->get(['id', 'code'])
            ->mapWithKeys(fn (Scope $scope): array => [$scope->id => ['is_default' => $scope->code === 'profile']])
            ->all()
    );

    return $client->fresh(['redirectUris', 'scopes', 'tokenPolicy']);
}

function issueClientCrudAuthorizationCode(SsoClient $client, User $user): array
{
    $verifier = 'client-secret-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $result = app(OAuthAuthorizationService::class)->approve($user, [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'profile',
        'state' => 'client-secret-hardening-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]);

    return [$result['code'], $verifier];
}

function assertClientSecretCanExchangeAuthorizationCode(SsoClient $client, string $plainSecret): void
{
    $oauthUser = User::factory()->create();
    [$code, $verifier] = issueClientCrudAuthorizationCode($client, $oauthUser);

    test()->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'code' => $code,
        'redirect_uri' => 'https://portal.example.com/callback',
        'code_verifier' => $verifier,
    ])->assertOk();
}

function assertClientSecretCannotExchangeAuthorizationCode(SsoClient $client, string $plainSecret): void
{
    $oauthUser = User::factory()->create();
    [$code, $verifier] = issueClientCrudAuthorizationCode($client, $oauthUser);

    test()->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'code' => $code,
        'redirect_uri' => 'https://portal.example.com/callback',
        'code_verifier' => $verifier,
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid client credentials.');
}

it('authorized user can view client index', function () {
    $plainSecret = 'index-visible-secret';
    $client = SsoClient::factory()->create([
        'name' => 'Portal Client',
        'client_id' => 'client_portal',
        'client_secret_hash' => Hash::make($plainSecret),
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
    $client->secrets()->create([
        'name' => 'Index secret',
        'secret_hash' => Hash::make($plainSecret),
        'last_four' => 'cret',
        'is_active' => true,
    ]);

    $user = clientManager(['clients.viewAny']);

    $response = $this->actingAs($user)
        ->get(route('admin.sso-clients.index', ['global' => 'Portal']));

    $response
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

    $response->assertDontSee($plainSecret);
    $response->assertDontSee('secret_hash');
    $response->assertDontSee('client_secret_hash');
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
            'default_scopes' => ['openid'],
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
    expect($client->client_type)->toBe(SsoClient::CLIENT_TYPE_CONFIDENTIAL);
    expect($client->client_secret_hash)->not->toBe('');
    expect(Hash::check($clientSecret['secret'], $client->client_secret_hash))->toBeTrue();
    expect($client->normalizedRedirectUris())->toBe(['https://portal.example.com/callback']);
    expect($client->normalizedScopeCodes())->toBe(['openid', 'profile']);
    expect($client->defaultScopeCodes())->toBe(['openid']);
    expect($client->trust_tier)->toBe(SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED);
    expect($client->is_first_party)->toBeTrue();
    expect($client->consent_bypass_allowed)->toBeFalse();
    expect($storedSecret)->not->toBeNull();
    expect(Hash::check($clientSecret['secret'], $storedSecret->secret_hash))->toBeTrue();
    expect(DB::table('client_secrets')->where('secret_hash', $clientSecret['secret'])->exists())->toBeFalse();
    expect(DB::table('sso_clients')->where('client_secret_hash', $clientSecret['secret'])->exists())->toBeFalse();

    $this->assertDatabaseHas('redirect_uris', [
        'sso_client_id' => $client->id,
        'uri' => 'https://portal.example.com/callback',
    ]);

    $this->assertDatabaseHas('client_scopes', [
        'sso_client_id' => $client->id,
        'scope_id' => Scope::query()->where('code', 'openid')->value('id'),
        'is_default' => true,
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

it('authorized user can store public client without generating a secret', function () {
    $user = clientManager(['clients.create']);
    $policy = TokenPolicy::factory()->create([
        'pkce_required' => true,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->post(route('admin.sso-clients.store'), [
            'name' => 'Desktop Public Client',
            'client_type' => SsoClient::CLIENT_TYPE_PUBLIC,
            'redirect_uris' => ['http://127.0.0.1:3978/callback'],
            'scopes' => ['openid', 'profile'],
            'default_scopes' => ['openid'],
            'is_active' => true,
            'token_policy_id' => $policy->id,
            'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
            'is_first_party' => false,
            'consent_bypass_allowed' => false,
        ]);

    $response
        ->assertRedirect(route('admin.sso-clients.index'))
        ->assertSessionHas('success')
        ->assertSessionMissing('clientSecret');

    $client = SsoClient::query()->with(['redirectUris', 'secrets'])->firstOrFail();

    expect($client->client_type)->toBe(SsoClient::CLIENT_TYPE_PUBLIC);
    expect($client->client_secret_hash)->toBe('');
    expect($client->secrets)->toHaveCount(0);
    expect($client->normalizedRedirectUris())->toBe(['http://127.0.0.1:3978/callback']);

    $this->assertDatabaseMissing('activity_log', [
        'log_name' => 'admin.client',
        'event' => 'admin.client_secret.created',
    ]);
});

it('rejects public client creation with a token policy that does not require pkce', function () {
    $user = clientManager(['clients.create']);
    $policy = TokenPolicy::factory()->create([
        'pkce_required' => false,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->from(route('admin.sso-clients.create'))
        ->post(route('admin.sso-clients.store'), [
            'name' => 'Unsafe Public Client',
            'client_type' => SsoClient::CLIENT_TYPE_PUBLIC,
            'redirect_uris' => ['https://portal.example.com/callback'],
            'scopes' => ['openid'],
            'default_scopes' => ['openid'],
            'is_active' => true,
            'token_policy_id' => $policy->id,
            'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
            'is_first_party' => false,
            'consent_bypass_allowed' => false,
        ])
        ->assertRedirect(route('admin.sso-clients.create'))
        ->assertSessionHasErrors(['token_policy_id']);
});

it('store validation fails when default scopes are not assigned to the client', function () {
    $user = clientManager(['clients.create']);

    $this->actingAs($user)
        ->from(route('admin.sso-clients.create'))
        ->post(route('admin.sso-clients.store'), [
            'name' => 'Portal Client',
            'redirect_uris' => ['https://portal.example.com/callback'],
            'scopes' => ['openid'],
            'default_scopes' => ['openid', 'profile'],
            'is_active' => true,
            'token_policy_id' => null,
            'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
            'is_first_party' => false,
            'consent_bypass_allowed' => false,
        ])
        ->assertRedirect(route('admin.sso-clients.create'))
        ->assertSessionHasErrors(['default_scopes']);
});

it('store validation rejects redirect uri fragments and duplicates', function () {
    $user = clientManager(['clients.create']);

    $this->actingAs($user)
        ->from(route('admin.sso-clients.create'))
        ->post(route('admin.sso-clients.store'), [
            'name' => 'Portal Client',
            'redirect_uris' => [
                'https://portal.example.com/callback#fragment',
                'https://portal.example.com/callback#fragment',
            ],
            'scopes' => ['openid'],
            'default_scopes' => ['openid'],
            'is_active' => true,
            'token_policy_id' => null,
            'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
            'is_first_party' => false,
            'consent_bypass_allowed' => false,
        ])
        ->assertRedirect(route('admin.sso-clients.create'))
        ->assertSessionHasErrors(['redirect_uris.0', 'redirect_uris.1']);
});

it('update validation rejects redirect uri fragments and duplicates', function () {
    $client = SsoClient::factory()->create();
    $user = clientManager(['clients.update']);

    $this->actingAs($user)
        ->from(route('admin.sso-clients.edit', $client))
        ->put(route('admin.sso-clients.update', $client), [
            'name' => 'Portal Client',
            'redirect_uris' => [
                'https://portal.example.com/callback',
                'https://portal.example.com/callback',
                'https://portal.example.com/fragment#bad',
            ],
            'scopes' => ['openid'],
            'default_scopes' => ['openid'],
            'is_active' => true,
            'token_policy_id' => null,
            'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
            'is_first_party' => false,
            'consent_bypass_allowed' => false,
        ])
        ->assertRedirect(route('admin.sso-clients.edit', $client))
        ->assertSessionHasErrors(['redirect_uris.1', 'redirect_uris.2']);
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
    $plainSecret = 'very-secret-value';
    $client = SsoClient::factory()->create([
        'name' => 'Portal Client',
        'client_id' => 'client_portal',
        'client_secret_hash' => Hash::make($plainSecret),
        'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED,
        'is_first_party' => true,
        'consent_bypass_allowed' => false,
    ]);
    $client->secrets()->create([
        'name' => 'Initial secret',
        'secret_hash' => Hash::make($plainSecret),
        'last_four' => '1234',
        'is_active' => true,
    ]);

    $user = clientManager(['clients.update', 'clients.manageSecrets']);
    $client->scopes()->sync(
        Scope::query()
            ->whereIn('code', ['openid', 'profile'])
            ->get(['id', 'code'])
            ->mapWithKeys(fn (Scope $scope): array => [$scope->id => ['is_default' => $scope->code === 'openid']])
            ->all()
    );

    $response = $this->actingAs($user)
        ->get(route('admin.sso-clients.edit', $client));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Edit')
            ->where('client.id', $client->id)
            ->where('client.name', 'Portal Client')
            ->where('client.clientId', 'client_portal')
            ->where('client.trustTier', SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED)
            ->where('client.isFirstParty', true)
            ->where('client.consentBypassAllowed', false)
            ->where('client.defaultScopes', ['openid'])
            ->where('client.secrets.0.lastFour', '1234')
            ->where('canManageSecrets', true)
            ->missing('client.clientSecret')
            ->missing('client.client_secret')
            ->has('trustTierOptions', 4));

    $response->assertDontSee($plainSecret);
    $response->assertDontSee('secret_hash');
    $response->assertDontSee('client_secret_hash');
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
            'default_scopes' => ['email'],
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
    expect($client->defaultScopeCodes())->toBe(['email']);
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

it('update validation fails when default scopes are not assigned to the client', function () {
    $client = SsoClient::factory()->create();
    $user = clientManager(['clients.update']);

    $this->actingAs($user)
        ->from(route('admin.sso-clients.edit', $client))
        ->put(route('admin.sso-clients.update', $client), [
            'name' => 'Portal Client',
            'redirect_uris' => ['https://portal.example.com/callback'],
            'scopes' => ['openid'],
            'default_scopes' => ['profile'],
            'is_active' => true,
            'token_policy_id' => null,
            'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
            'is_first_party' => false,
            'consent_bypass_allowed' => false,
        ])
        ->assertRedirect(route('admin.sso-clients.edit', $client))
        ->assertSessionHasErrors(['default_scopes']);
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
    $oldPlainSecret = 'old-secret-value';
    $client = SsoClient::factory()->create([
        'name' => 'Portal Client',
        'client_id' => 'client_portal',
        'client_secret_hash' => Hash::make($oldPlainSecret),
    ]);
    $client = prepareClientForSecretTokenExchange($client);
    $oldSecret = $client->secrets()->create([
        'name' => 'Initial secret',
        'secret_hash' => Hash::make($oldPlainSecret),
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
    expect(DB::table('client_secrets')->where('secret_hash', $sessionSecret)->exists())->toBeFalse();
    expect(DB::table('sso_clients')->where('client_secret_hash', $sessionSecret)->exists())->toBeFalse();

    $tokenCountBeforeOldSecretAttempt = Token::query()->count();
    assertClientSecretCannotExchangeAuthorizationCode($client->fresh(['redirectUris', 'scopes', 'tokenPolicy']), $oldPlainSecret);
    expect(Token::query()->count())->toBe($tokenCountBeforeOldSecretAttempt);

    assertClientSecretCanExchangeAuthorizationCode($client->fresh(['redirectUris', 'scopes', 'tokenPolicy']), $sessionSecret);

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
    $olderPlainSecret = 'older-secret';
    $revokedPlainSecret = 'newest-secret';
    $client = SsoClient::factory()->create([
        'client_secret_hash' => Hash::make($olderPlainSecret),
    ]);
    $client = prepareClientForSecretTokenExchange($client);
    $client->secrets()->create([
        'name' => 'Older secret',
        'secret_hash' => Hash::make($olderPlainSecret),
        'last_four' => '1111',
        'is_active' => true,
    ]);
    $secretToRevoke = $client->secrets()->create([
        'name' => 'Newest secret',
        'secret_hash' => Hash::make($revokedPlainSecret),
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

    $tokenCountBeforeRevokedSecretAttempt = Token::query()->count();
    assertClientSecretCannotExchangeAuthorizationCode($client->fresh(['redirectUris', 'scopes', 'tokenPolicy']), $revokedPlainSecret);
    expect(Token::query()->count())->toBe($tokenCountBeforeRevokedSecretAttempt);

    assertClientSecretCanExchangeAuthorizationCode($client->fresh(['redirectUris', 'scopes', 'tokenPolicy']), $olderPlainSecret);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'admin.client',
        'event' => 'admin.client_secret.revoked',
    ]);

    $revokeActivity = Activity::query()
        ->where('event', 'admin.client_secret.revoked')
        ->latest()
        ->firstOrFail();

    expect($revokeActivity->properties->toArray())->toHaveKey('secret_last_four')
        ->and($revokeActivity->properties->toArray())->not->toHaveKeys(['secret', 'client_secret']);
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
