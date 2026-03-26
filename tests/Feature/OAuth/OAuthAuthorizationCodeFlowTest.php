<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ClientSecret;
use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\Token;
use App\Models\TokenPolicy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->withoutVite();

    Scope::factory()->create(['name' => 'OpenID', 'code' => 'openid', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Profile', 'code' => 'profile', 'is_active' => true]);
});

function oauthClient(array $overrides = []): array
{
    $policy = TokenPolicy::query()->create(array_merge([
        'name' => 'Strict Public Policy',
        'code' => 'strict.public.'.fake()->unique()->numerify('###'),
        'description' => 'PKCE required test policy.',
        'access_token_ttl_minutes' => 60,
        'refresh_token_ttl_minutes' => 1440,
        'refresh_token_rotation_enabled' => true,
        'pkce_required' => true,
        'reuse_refresh_token_forbidden' => true,
        'is_default' => false,
        'is_active' => true,
    ], $overrides['policy'] ?? []));

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

    $plainSecret = 'super-secret-value';
    $client->secrets()->create([
        'name' => 'Initial secret',
        'secret_hash' => Hash::make($plainSecret),
        'last_four' => 'alue',
        'is_active' => true,
    ]);

    return [$client->fresh(['redirectUris', 'scopes', 'tokenPolicy']), $policy, $plainSecret];
}

it('issues an authorization code and redirects back to the registered callback', function () {
    [$client] = oauthClient();
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $response = $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'abc123',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->toStartWith('https://portal.example.com/callback?');
    parse_str(parse_url($location, PHP_URL_QUERY) ?: '', $query);

    expect($query['state'] ?? null)->toBe('abc123');
    expect($query['code'] ?? null)->not->toBeNull();

    $this->assertDatabaseHas('authorization_codes', [
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'code_hash' => hash('sha256', (string) $query['code']),
    ]);
});

it('exchanges authorization code for tokens with valid pkce verifier', function () {
    [$client, $policy, $plainSecret] = oauthClient();
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $authorize = $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'oauth-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    parse_str(parse_url($authorize->headers->get('Location'), PHP_URL_QUERY) ?: '', $query);

    $tokenResponse = $this->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'code' => $query['code'],
        'redirect_uri' => 'https://portal.example.com/callback',
        'code_verifier' => $verifier,
    ]);

    $tokenResponse
        ->assertOk()
        ->assertJsonPath('message', 'OAuth token issued successfully.')
        ->assertJsonPath('data.token_type', 'Bearer');

    $data = $tokenResponse->json('data');
    expect($data['access_token'])->not->toBeEmpty()
        ->and($data['refresh_token'])->not->toBeEmpty()
        ->and($data['scope'])->toBe('openid profile');

    $this->assertDatabaseHas('tokens', [
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', $data['access_token']),
        'refresh_token_hash' => hash('sha256', $data['refresh_token']),
    ]);

    $this->assertDatabaseHas('authorization_codes', [
        'code_hash' => hash('sha256', $query['code']),
    ]);
    expect(Token::query()->count())->toBe(1);
});

it('rejects token exchange when pkce verifier is invalid', function () {
    [$client, , $plainSecret] = oauthClient();
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $authorize = $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    parse_str(parse_url($authorize->headers->get('Location'), PHP_URL_QUERY) ?: '', $query);

    $this->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'code' => $query['code'],
        'redirect_uri' => 'https://portal.example.com/callback',
        'code_verifier' => 'wrong-verifier',
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'OAuth token request failed.')
        ->assertJsonStructure(['errors' => ['code_verifier']]);
});

it('rotates refresh token on refresh grant when policy requires rotation', function () {
    [$client, , $plainSecret] = oauthClient();
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $authorize = $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    parse_str(parse_url($authorize->headers->get('Location'), PHP_URL_QUERY) ?: '', $query);

    $firstTokenResponse = $this->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'code' => $query['code'],
        'redirect_uri' => 'https://portal.example.com/callback',
        'code_verifier' => $verifier,
    ])->assertOk();

    $originalRefreshToken = $firstTokenResponse->json('data.refresh_token');

    $refreshResponse = $this->postJson(route('oauth.token'), [
        'grant_type' => 'refresh_token',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'refresh_token' => $originalRefreshToken,
    ]);

    $refreshResponse->assertOk();
    $newRefreshToken = $refreshResponse->json('data.refresh_token');

    expect($newRefreshToken)->not->toBe($originalRefreshToken);

    $oldToken = Token::query()->where('refresh_token_hash', hash('sha256', $originalRefreshToken))->firstOrFail();
    expect($oldToken->refresh_token_revoked_at)->not->toBeNull();
});

it('continues the intended authorize flow after login and returns an inertia external redirect', function () {
    [$client] = oauthClient();
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $authorizeUrl = route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'local-dev-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ], false);

    $this->get($authorizeUrl)
        ->assertRedirect(route('login'));

    $loginResponse = $this
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

    $loginRedirect = $loginResponse->headers->get('Location');

    expect($loginRedirect)->not->toBeNull()
        ->and(parse_url($loginRedirect, PHP_URL_PATH))->toBe('/oauth/authorize');

    parse_str(parse_url($loginRedirect, PHP_URL_QUERY) ?: '', $loginQuery);

    expect($loginQuery['response_type'] ?? null)->toBe('code');
    expect($loginQuery['client_id'] ?? null)->toBe($client->client_id);
    expect($loginQuery['redirect_uri'] ?? null)->toBe('https://portal.example.com/callback');
    expect($loginQuery['scope'] ?? null)->toBe('openid profile');
    expect($loginQuery['state'] ?? null)->toBe('local-dev-state');
    expect($loginQuery['code_challenge'] ?? null)->toBe($challenge);
    expect($loginQuery['code_challenge_method'] ?? null)->toBe('S256');

    $response = $this
        ->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', (string) app(HandleInertiaRequests::class)->version(Request::create($loginRedirect, 'GET')))
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->get($loginRedirect);

    $response->assertStatus(409);

    $location = $response->headers->get('X-Inertia-Location');

    expect($location)->not->toBeNull()
        ->and($location)->toStartWith('https://portal.example.com/callback?');

    parse_str(parse_url($location, PHP_URL_QUERY) ?: '', $query);

    expect($query['state'] ?? null)->toBe('local-dev-state');
    expect($query['code'] ?? null)->not->toBeNull();

    $this->assertDatabaseHas('authorization_codes', [
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'code_hash' => hash('sha256', (string) $query['code']),
    ]);
});
