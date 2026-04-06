<?php

use App\Models\AuthorizationCode;
use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use App\Models\User;

beforeEach(function (): void {
    $this->withoutVite();

    Scope::factory()->create(['name' => 'OpenID', 'code' => 'openid', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Profile', 'code' => 'profile', 'is_active' => true]);
});

function consentDenyClient(array $policyOverrides = []): SsoClient
{
    $policy = TokenPolicy::query()->create(array_merge([
        'name' => 'Consent Deny Policy',
        'code' => 'consent.deny.'.fake()->unique()->numerify('###'),
        'description' => 'Consent deny policy.',
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

    return $client->fresh(['redirectUris', 'scopes', 'tokenPolicy']);
}

function consentDenyAuthorizeParams(SsoClient $client, array $overrides = []): array
{
    return array_merge([
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'denied-state',
        'nonce' => 'deny-nonce',
        'code_challenge' => 'test-code-challenge',
        'code_challenge_method' => 'S256',
    ], $overrides);
}

function prepareDenyConsentTokenFor(User $user, SsoClient $client, array $overrides = []): string
{
    $response = test()->actingAs($user)->get(route('oauth.authorize', consentDenyAuthorizeParams($client, $overrides)));

    expect($response->viewData('page')['props']['consentToken'] ?? null)->not->toBeNull();
    expect(AuthorizationCode::query()->count())->toBe(0);

    return (string) $response->viewData('page')['props']['consentToken'];
}

it('denies a valid consent token and redirects back with access_denied and state', function (): void {
    $client = consentDenyClient();
    $user = User::factory()->create();
    $token = prepareDenyConsentTokenFor($user, $client);

    $response = $this->actingAs($user)->post(route('oauth.authorize.deny'), [
        'consent_token' => $token,
    ]);

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->not->toBeNull()
        ->and($location)->toStartWith('https://portal.example.com/callback?');

    parse_str(parse_url($location, PHP_URL_QUERY) ?: '', $query);

    expect($query['error'] ?? null)->toBe('access_denied')
        ->and($query['error_description'] ?? null)->toBe('Access to this client was denied.')
        ->and($query['state'] ?? null)->toBe('denied-state');

    expect(AuthorizationCode::query()->count())->toBe(0);
    expect(session('oauth.consent_contexts', []))->not->toHaveKey($token);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.consent.denied',
        'description' => 'OAuth consent denied.',
    ]);
});

it('omits state from the refusal callback when the original authorize request had none', function (): void {
    $client = consentDenyClient();
    $user = User::factory()->create();
    $token = prepareDenyConsentTokenFor($user, $client, ['state' => null]);

    $response = $this->actingAs($user)->post(route('oauth.authorize.deny'), [
        'consent_token' => $token,
    ]);

    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY) ?: '', $query);

    expect($query['error'] ?? null)->toBe('access_denied')
        ->and($query)->not->toHaveKey('state');
});

it('does not issue an authorization code when consent is denied', function (): void {
    $client = consentDenyClient();
    $user = User::factory()->create();
    $token = prepareDenyConsentTokenFor($user, $client);

    $this->actingAs($user)->post(route('oauth.authorize.deny'), [
        'consent_token' => $token,
    ])->assertRedirect();

    expect(AuthorizationCode::query()->count())->toBe(0);
});

it('fails when the deny consent token is missing', function (): void {
    $user = User::factory()->create();

    $this->from('/oauth/authorize')
        ->actingAs($user)
        ->post(route('oauth.authorize.deny'), [])
        ->assertRedirect('/oauth/authorize')
        ->assertSessionHasErrors(['consent_token']);

    expect(AuthorizationCode::query()->count())->toBe(0);
});

it('fails when the deny consent token is invalid', function (): void {
    $user = User::factory()->create();

    $this->from('/oauth/authorize')
        ->actingAs($user)
        ->post(route('oauth.authorize.deny'), [
            'consent_token' => str_repeat('b', 64),
        ])
        ->assertRedirect('/oauth/authorize')
        ->assertSessionHasErrors([
            'consent_token' => 'The consent decision is missing, expired, or no longer valid.',
        ]);

    expect(AuthorizationCode::query()->count())->toBe(0);
});

it('fails when the deny consent token is expired and cleans it up', function (): void {
    $client = consentDenyClient();
    $user = User::factory()->create();
    $token = prepareDenyConsentTokenFor($user, $client);

    $stored = session('oauth.consent_contexts', []);
    $stored[$token]['expires_at'] = now()->subMinute()->toIso8601String();
    session()->put('oauth.consent_contexts', $stored);

    $this->from('/oauth/authorize')
        ->actingAs($user)
        ->post(route('oauth.authorize.deny'), [
            'consent_token' => $token,
        ])
        ->assertRedirect('/oauth/authorize')
        ->assertSessionHasErrors([
            'consent_token' => 'The consent decision is missing, expired, or no longer valid.',
        ]);

    expect(session('oauth.consent_contexts', []))->not->toHaveKey($token);
    expect(AuthorizationCode::query()->count())->toBe(0);
});

it('fails when the same deny consent token is reused after a successful denial', function (): void {
    $client = consentDenyClient();
    $user = User::factory()->create();
    $token = prepareDenyConsentTokenFor($user, $client);

    $this->actingAs($user)->post(route('oauth.authorize.deny'), [
        'consent_token' => $token,
    ])->assertRedirect();

    $this->from('/oauth/authorize')
        ->actingAs($user)
        ->post(route('oauth.authorize.deny'), [
            'consent_token' => $token,
        ])
        ->assertRedirect('/oauth/authorize')
        ->assertSessionHasErrors([
            'consent_token' => 'The consent decision is missing, expired, or no longer valid.',
        ]);

    expect(AuthorizationCode::query()->count())->toBe(0);
});

it('fails when the deny consent token belongs to a different authenticated user', function (): void {
    $client = consentDenyClient();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = prepareDenyConsentTokenFor($owner, $client);

    $this->from('/oauth/authorize')
        ->actingAs($otherUser)
        ->post(route('oauth.authorize.deny'), [
            'consent_token' => $token,
        ])
        ->assertRedirect('/oauth/authorize')
        ->assertSessionHasErrors([
            'consent_token' => 'The consent decision does not belong to the current user session.',
        ]);

    expect(session('oauth.consent_contexts', []))->not->toHaveKey($token);
    expect(AuthorizationCode::query()->count())->toBe(0);
});

it('fails when the deny consent token is submitted from a different session', function (): void {
    $client = consentDenyClient();
    $user = User::factory()->create();
    $token = prepareDenyConsentTokenFor($user, $client);

    session()->flush();

    $this->from('/oauth/authorize')
        ->actingAs($user)
        ->post(route('oauth.authorize.deny'), [
            'consent_token' => $token,
        ])
        ->assertRedirect('/oauth/authorize')
        ->assertSessionHasErrors([
            'consent_token' => 'The consent decision is missing, expired, or no longer valid.',
        ]);

    expect(AuthorizationCode::query()->count())->toBe(0);
});
