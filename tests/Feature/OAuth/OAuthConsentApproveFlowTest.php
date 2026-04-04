<?php

use App\Models\AuthorizationCode;
use App\Models\ClientSecret;
use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Models\UserClientConsent;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->withoutVite();

    Scope::factory()->create(['name' => 'OpenID', 'code' => 'openid', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Profile', 'code' => 'profile', 'is_active' => true]);
});

function consentApproveClient(array $policyOverrides = []): SsoClient
{
    $policy = TokenPolicy::query()->create(array_merge([
        'name' => 'Consent Approve Policy',
        'code' => 'consent.approve.'.fake()->unique()->numerify('###'),
        'description' => 'Consent approve policy.',
        'access_token_ttl_minutes' => 60,
        'refresh_token_ttl_minutes' => 1440,
        'refresh_token_rotation_enabled' => true,
        'pkce_required' => true,
        'reuse_refresh_token_forbidden' => true,
        'is_default' => false,
        'is_active' => true,
    ], $policyOverrides));

    $client = SsoClient::factory()->create([
        'name' => 'Portal Client',
        'client_id' => 'client_portal_'.fake()->unique()->numerify('###'),
        'token_policy_id' => $policy->id,
        'is_active' => true,
    ]);

    $client->redirectUris()->create([
        'uri' => 'https://portal.example.com/callback',
        'uri_hash' => hash('sha256', 'https://portal.example.com/callback'),
        'is_primary' => true,
    ]);
    $client->scopes()->sync(Scope::query()->whereIn('code', ['openid', 'profile'])->pluck('id')->all());
    $client->secrets()->create([
        'name' => 'Initial secret',
        'secret_hash' => Hash::make('super-secret-value'),
        'last_four' => 'alue',
        'is_active' => true,
    ]);

    return $client->fresh(['redirectUris', 'scopes', 'tokenPolicy']);
}

function consentAuthorizeParams(SsoClient $client, array $overrides = []): array
{
    return array_merge([
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'approved-state',
        'code_challenge' => 'test-code-challenge',
        'code_challenge_method' => 'S256',
    ], $overrides);
}

function prepareConsentTokenFor(User $user, SsoClient $client, array $overrides = []): string
{
    $response = test()->actingAs($user)->get(route('oauth.authorize', consentAuthorizeParams($client, $overrides)));

    expect($response->viewData('page')['props']['consentToken'] ?? null)->not->toBeNull();
    expect(AuthorizationCode::query()->count())->toBe(0);

    return (string) $response->viewData('page')['props']['consentToken'];
}

it('approves a valid consent token and redirects back with code and state', function (): void {
    $client = consentApproveClient();
    $user = User::factory()->create();
    $token = prepareConsentTokenFor($user, $client);

    $response = $this->actingAs($user)->post(route('oauth.authorize.approve'), [
        'consent_token' => $token,
    ]);

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->not->toBeNull()
        ->and($location)->toStartWith('https://portal.example.com/callback?');

    parse_str(parse_url($location, PHP_URL_QUERY) ?: '', $query);

    expect($query['code'] ?? null)->not->toBeNull()
        ->and($query['state'] ?? null)->toBe('approved-state');

    $this->assertDatabaseHas('authorization_codes', [
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'code_hash' => hash('sha256', (string) $query['code']),
    ]);

    $grant = UserClientConsent::query()->where('user_id', $user->id)->where('client_id', $client->id)->first();

    expect($grant)->not->toBeNull()
        ->and($grant?->granted_scope_codes)->toBe(['openid', 'profile'])
        ->and($grant?->revoked_at)->toBeNull();

    expect(session('oauth.consent_contexts', []))->not->toHaveKey($token);
    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.consent.approved',
        'description' => 'OAuth consent approved.',
    ]);
});

it('omits state from the callback when the original authorize request had none', function (): void {
    $client = consentApproveClient();
    $user = User::factory()->create();
    $token = prepareConsentTokenFor($user, $client, ['state' => null]);

    $response = $this->actingAs($user)->post(route('oauth.authorize.approve'), [
        'consent_token' => $token,
    ]);

    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY) ?: '', $query);

    expect($query)->toHaveKey('code')
        ->and($query)->not->toHaveKey('state');
});

it('does not issue an authorization code until approve is submitted', function (): void {
    $client = consentApproveClient();
    $user = User::factory()->create();

    prepareConsentTokenFor($user, $client);

    expect(AuthorizationCode::query()->count())->toBe(0);
});

it('fails when the consent token is missing', function (): void {
    $user = User::factory()->create();

    $this->from('/oauth/authorize')
        ->actingAs($user)
        ->post(route('oauth.authorize.approve'), [])
        ->assertRedirect('/oauth/authorize')
        ->assertSessionHasErrors(['consent_token']);

    expect(AuthorizationCode::query()->count())->toBe(0);
});

it('fails when the consent token is invalid', function (): void {
    $user = User::factory()->create();

    $this->from('/oauth/authorize')
        ->actingAs($user)
        ->post(route('oauth.authorize.approve'), [
            'consent_token' => str_repeat('a', 64),
        ])
        ->assertRedirect('/oauth/authorize')
        ->assertSessionHasErrors([
            'consent_token' => 'The consent decision is missing, expired, or no longer valid.',
        ]);

    expect(AuthorizationCode::query()->count())->toBe(0);
});

it('fails when the consent token is expired and cleans it up', function (): void {
    $client = consentApproveClient();
    $user = User::factory()->create();
    $token = prepareConsentTokenFor($user, $client);

    $stored = session('oauth.consent_contexts', []);
    $stored[$token]['expires_at'] = now()->subMinute()->toIso8601String();
    session()->put('oauth.consent_contexts', $stored);

    $this->from('/oauth/authorize')
        ->actingAs($user)
        ->post(route('oauth.authorize.approve'), [
            'consent_token' => $token,
        ])
        ->assertRedirect('/oauth/authorize')
        ->assertSessionHasErrors([
            'consent_token' => 'The consent decision is missing, expired, or no longer valid.',
        ]);

    expect(session('oauth.consent_contexts', []))->not->toHaveKey($token);
    expect(AuthorizationCode::query()->count())->toBe(0);
});

it('fails when the same consent token is reused after a successful approval', function (): void {
    $client = consentApproveClient();
    $user = User::factory()->create();
    $token = prepareConsentTokenFor($user, $client);

    $first = $this->actingAs($user)->post(route('oauth.authorize.approve'), [
        'consent_token' => $token,
    ]);

    $first->assertRedirect();

    $this->from('/oauth/authorize')
        ->actingAs($user)
        ->post(route('oauth.authorize.approve'), [
            'consent_token' => $token,
        ])
        ->assertRedirect('/oauth/authorize')
        ->assertSessionHasErrors([
            'consent_token' => 'The consent decision is missing, expired, or no longer valid.',
        ]);

    expect(AuthorizationCode::query()->count())->toBe(1);
});

it('fails when the consent token belongs to a different authenticated user', function (): void {
    $client = consentApproveClient();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = prepareConsentTokenFor($owner, $client);

    $this->from('/oauth/authorize')
        ->actingAs($otherUser)
        ->post(route('oauth.authorize.approve'), [
            'consent_token' => $token,
        ])
        ->assertRedirect('/oauth/authorize')
        ->assertSessionHasErrors([
            'consent_token' => 'The consent decision does not belong to the current user session.',
        ]);

    expect(session('oauth.consent_contexts', []))->not->toHaveKey($token);
    expect(AuthorizationCode::query()->count())->toBe(0);
});

it('fails when the consent token is submitted from a different session', function (): void {
    $client = consentApproveClient();
    $user = User::factory()->create();
    $token = prepareConsentTokenFor($user, $client);

    session()->flush();

    $this->from('/oauth/authorize')
        ->actingAs($user)
        ->post(route('oauth.authorize.approve'), [
            'consent_token' => $token,
        ])
        ->assertRedirect('/oauth/authorize')
        ->assertSessionHasErrors([
            'consent_token' => 'The consent decision is missing, expired, or no longer valid.',
        ]);

    expect(AuthorizationCode::query()->count())->toBe(0);
});
