<?php

use App\Models\ClientUserAccess;
use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->withoutVite();

    Scope::factory()->create(['name' => 'OpenID', 'code' => 'openid', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Profile', 'code' => 'profile', 'is_active' => true]);
});

function accessControlledOauthClient(array $clientOverrides = []): SsoClient
{
    $policy = TokenPolicy::query()->create([
        'name' => 'Default Policy',
        'code' => 'default.policy.'.fake()->unique()->numerify('###'),
        'description' => 'Default access policy.',
        'access_token_ttl_minutes' => 60,
        'refresh_token_ttl_minutes' => 1440,
        'refresh_token_rotation_enabled' => true,
        'pkce_required' => true,
        'reuse_refresh_token_forbidden' => true,
        'is_default' => false,
        'is_active' => true,
    ]);

    $client = SsoClient::factory()->create(array_merge([
        'name' => 'Portal Client',
        'client_id' => 'client_portal_'.fake()->unique()->numerify('###'),
        'token_policy_id' => $policy->id,
        'is_active' => true,
    ], $clientOverrides));

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

function authorizeParams(SsoClient $client): array
{
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    return [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'client-access-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ];
}

it('allows authorization for open clients without access records', function () {
    $client = accessControlledOauthClient();
    $user = User::factory()->create(['is_active' => true]);

    $response = $this->actingAs($user)->get(route('oauth.authorize', authorizeParams($client)));

    $response->assertRedirect();

    $this->assertDatabaseHas('authorization_codes', [
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
    ]);
});

it('allows authorization for restricted clients only when the user has an active access record', function () {
    $client = accessControlledOauthClient();
    $allowedUser = User::factory()->create(['is_active' => true]);
    User::factory()->create(['is_active' => true]);

    ClientUserAccess::factory()->create([
        'client_id' => $client->id,
        'user_id' => $allowedUser->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($allowedUser)->get(route('oauth.authorize', authorizeParams($client)));

    $response->assertRedirect();

    $this->assertDatabaseHas('authorization_codes', [
        'sso_client_id' => $client->id,
        'user_id' => $allowedUser->id,
    ]);
});

it('denies authorization for restricted clients when the user is not explicitly allowed', function () {
    $client = accessControlledOauthClient();
    $allowedUser = User::factory()->create(['is_active' => true]);
    $blockedUser = User::factory()->create(['is_active' => true]);

    ClientUserAccess::factory()->create([
        'client_id' => $client->id,
        'user_id' => $allowedUser->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($blockedUser)
        ->get(route('oauth.authorize', authorizeParams($client)));

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->not->toBeNull();
    expect($location)->toContain('https://portal.example.com/callback');
    expect($location)->toContain('error=access_denied');
    expect($location)->toContain('error_description=Access+to+this+client+was+denied.');
    expect($location)->toContain('state=client-access-state');

    expect(Activity::query()->latest()->firstOrFail()->properties->get('reason'))->toBe('missing_active_access');
    expect(\App\Models\AuthorizationCode::query()->count())->toBe(0);
});

it('denies inactive users from authorizing clients', function () {
    $client = accessControlledOauthClient();
    $user = User::factory()->create(['is_active' => false]);

    $response = $this->actingAs($user)
        ->get(route('oauth.authorize', authorizeParams($client)));

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->not->toBeNull();
    expect($location)->toContain('error=access_denied');
    expect($location)->toContain('state=client-access-state');

    expect(Activity::query()->latest()->firstOrFail()->properties->get('reason'))->toBe('inactive_user');
});

it('denies authorization when the client is inactive', function () {
    $client = accessControlledOauthClient(['is_active' => false]);
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->get(route('oauth.authorize', authorizeParams($client)))
        ->assertStatus(302)
        ->assertSessionHasErrors([
            'client_id' => 'The provided client is invalid or inactive.',
        ]);
});

it('denies authorization before allowed_from', function () {
    $client = accessControlledOauthClient();
    $user = User::factory()->create(['is_active' => true]);

    ClientUserAccess::factory()->create([
        'client_id' => $client->id,
        'user_id' => $user->id,
        'is_active' => true,
        'allowed_from' => now()->addHour(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('oauth.authorize', authorizeParams($client)));

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->not->toBeNull();
    expect($location)->toContain('error=access_denied');
    expect($location)->toContain('state=client-access-state');

    expect(Activity::query()->latest()->firstOrFail()->properties->get('reason'))->toBe('before_allowed_from');
});

it('denies authorization after allowed_until expires', function () {
    $client = accessControlledOauthClient();
    $user = User::factory()->create(['is_active' => true]);

    ClientUserAccess::factory()->create([
        'client_id' => $client->id,
        'user_id' => $user->id,
        'is_active' => true,
        'allowed_until' => now()->subMinute(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('oauth.authorize', authorizeParams($client)));

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->not->toBeNull();
    expect($location)->toContain('error=access_denied');
    expect($location)->toContain('state=client-access-state');

    expect(Activity::query()->latest()->firstOrFail()->properties->get('reason'))->toBe('after_allowed_until');
});

it('allows authorization inside an active date window', function () {
    $client = accessControlledOauthClient();
    $user = User::factory()->create(['is_active' => true]);

    ClientUserAccess::factory()->create([
        'client_id' => $client->id,
        'user_id' => $user->id,
        'is_active' => true,
        'allowed_from' => now()->subHour(),
        'allowed_until' => now()->addHour(),
    ]);

    $response = $this->actingAs($user)->get(route('oauth.authorize', authorizeParams($client)));

    $response->assertRedirect();
    $this->assertDatabaseHas('authorization_codes', [
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
    ]);
});

it('audits denied authorization attempts caused by client access restrictions', function () {
    $client = accessControlledOauthClient();
    $user = User::factory()->create(['is_active' => true]);
    User::factory()->create(['is_active' => true]);

    ClientUserAccess::factory()->create([
        'client_id' => $client->id,
        'user_id' => User::factory()->create(['is_active' => true])->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('oauth.authorize', authorizeParams($client)));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('error=access_denied');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.authorization.denied',
        'description' => 'OAuth authorization denied.',
        'causer_id' => $user->id,
    ]);
});
