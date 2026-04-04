<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AuthorizationCode;
use App\Models\ClientSecret;
use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\Token;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Models\UserClientConsent;
use App\Services\OAuth\OAuthAuthorizationService;
use App\Services\OAuth\OAuthRememberedConsentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

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
        ...($overrides['client'] ?? []),
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

function issueAuthorizationCodeForOauthClient(User $user, SsoClient $client, array $overrides = []): array
{
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $payload = array_merge([
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'oauth-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ], $overrides);

    $result = app(OAuthAuthorizationService::class)->approve($user, $payload);

    return [$result['code'], $verifier, $payload];
}

it('renders the consent page and stores a consent context for valid authorize requests', function () {
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

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('OAuth/Consent')
            ->where('type', 'consent')
            ->where('client.name', 'Portal Client')
            ->where('scopes.0.name', 'OpenID')
            ->where('scopes.1.name', 'Profile')
            ->where('summary.title', 'Portal Client is requesting access to your account.')
            ->has('consentToken'));

    $consentToken = $response->viewData('page')['props']['consentToken'] ?? null;
    $storedContext = session('oauth.consent_contexts.'.$consentToken);

    expect($consentToken)->not->toBeNull()
        ->and($storedContext['client_id'] ?? null)->toBe($client->client_id)
        ->and($storedContext['user_id'] ?? null)->toBe($user->id)
        ->and($storedContext['state'] ?? null)->toBe('abc123');

    expect(AuthorizationCode::query()->count())->toBe(0);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.trust_decision.show_consent',
        'description' => 'OAuth trust policy decision evaluated.',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.remembered_consent.not_used',
        'description' => 'OAuth remembered consent decision evaluated.',
    ]);
});

it('skips consent for trusted first-party clients when bypass is allowed', function () {
    [$client] = oauthClient([
        'client' => [
            'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_TRUSTED,
            'is_first_party' => true,
            'consent_bypass_allowed' => true,
        ],
    ]);
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $response = $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'skip-consent-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->not->toBeNull()
        ->and($location)->toStartWith('https://portal.example.com/callback?');

    parse_str(parse_url($location, PHP_URL_QUERY) ?: '', $query);

    expect($query['code'] ?? null)->not->toBeNull()
        ->and($query['state'] ?? null)->toBe('skip-consent-state');

    $this->assertDatabaseHas('authorization_codes', [
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'code_hash' => hash('sha256', (string) $query['code']),
    ]);

    expect(session('oauth.consent_contexts', []))->toBe([]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.trust_decision.skip_consent',
        'description' => 'OAuth trust policy decision evaluated.',
    ]);
});

it('denies interactive authorization for machine-to-machine trust tier clients', function () {
    [$client] = oauthClient([
        'client' => [
            'trust_tier' => SsoClient::TRUST_TIER_MACHINE_TO_MACHINE,
            'is_first_party' => false,
            'consent_bypass_allowed' => false,
        ],
    ]);
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $response = $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'machine-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->not->toBeNull();
    expect($location)->toContain('https://portal.example.com/callback');
    expect($location)->toContain('error=access_denied');
    expect($location)->toContain('state=machine-state');

    expect(AuthorizationCode::query()->count())->toBe(0);
    expect(session('oauth.consent_contexts', []))->toBe([]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.trust_decision.deny_authorization',
        'description' => 'OAuth trust policy decision evaluated.',
    ]);
});

it('skips consent by reusing remembered consent when trust decision would otherwise show consent', function () {
    [$client] = oauthClient([
        'client' => [
            'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
            'is_first_party' => false,
            'consent_bypass_allowed' => false,
        ],
    ]);
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    app(OAuthRememberedConsentService::class)->storeApprovedConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    $response = $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'remembered-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->not->toBeNull()
        ->and($location)->toStartWith('https://portal.example.com/callback?');

    parse_str(parse_url($location, PHP_URL_QUERY) ?: '', $query);

    expect($query['code'] ?? null)->not->toBeNull()
        ->and($query['state'] ?? null)->toBe('remembered-state');

    expect(AuthorizationCode::query()->count())->toBe(1);
    expect(UserClientConsent::query()->count())->toBe(1);
    expect(session('oauth.consent_contexts', []))->toBe([]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.remembered_consent.used',
        'description' => 'OAuth remembered consent decision evaluated.',
    ]);
});

it('renders consent when remembered consent exists but scope does not exactly match', function () {
    [$client] = oauthClient([
        'client' => [
            'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
            'is_first_party' => false,
            'consent_bypass_allowed' => false,
        ],
    ]);
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    app(OAuthRememberedConsentService::class)->storeApprovedConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid'],
        redirectUri: 'https://portal.example.com/callback',
    );

    $response = $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'scope-mismatch-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('OAuth/Consent')
            ->where('client.name', 'Portal Client')
            ->has('consentToken'));

    expect(AuthorizationCode::query()->count())->toBe(0);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.remembered_consent.mismatch',
        'description' => 'OAuth remembered consent decision evaluated.',
    ]);
});

it('does not let remembered consent override trust-based deny decisions', function () {
    [$client] = oauthClient([
        'client' => [
            'trust_tier' => SsoClient::TRUST_TIER_MACHINE_TO_MACHINE,
            'is_first_party' => false,
            'consent_bypass_allowed' => false,
        ],
    ]);
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    app(OAuthRememberedConsentService::class)->storeApprovedConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    $response = $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'deny-before-remembered',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->not->toBeNull();
    expect($location)->toContain('error=access_denied');
    expect($location)->toContain('state=deny-before-remembered');
    expect(AuthorizationCode::query()->count())->toBe(0);

    $this->assertDatabaseMissing('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.remembered_consent.used',
    ]);
});

it('rejects authorize requests when the redirect uri does not strictly match the registered client uri', function () {
    [$client] = oauthClient();
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback/',
        'scope' => 'openid profile',
        'state' => 'strict-redirect-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))
        ->assertStatus(302)
        ->assertSessionHasErrors([
            'redirect_uri' => 'The redirect URI does not match the registered client redirect URIs.',
        ]);

    expect(AuthorizationCode::query()->count())->toBe(0);
});

it('rejects authorize requests when the client requests a scope it is not allowed to use', function () {
    [$client] = oauthClient();
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    Scope::factory()->create([
        'name' => 'Email',
        'code' => 'email',
        'is_active' => true,
    ]);

    $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid email',
        'state' => 'invalid-scope-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))
        ->assertStatus(302)
        ->assertSessionHasErrors([
            'scope' => 'The requested scope [email] is not allowed for this client.',
        ]);

    expect(AuthorizationCode::query()->count())->toBe(0);
});

it('rejects authorize requests when the client is invalid with a validation error instead of 404', function () {
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => 'missing-client',
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'invalid-client-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))
        ->assertStatus(302)
        ->assertSessionHasErrors([
            'client_id' => 'The provided client is invalid or inactive.',
        ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.authorization.denied',
        'description' => 'OAuth authorization denied.',
    ]);
});

it('rejects authorize requests when the code challenge method is plain', function () {
    [$client] = oauthClient();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'plain-method-state',
        'code_challenge' => 'plain-verifier-value',
        'code_challenge_method' => 'plain',
    ]))
        ->assertStatus(302)
        ->assertSessionHasErrors([
            'code_challenge_method' => 'The selected code challenge method is invalid.',
        ]);

    expect(AuthorizationCode::query()->count())->toBe(0);
});

it('rejects authorize requests when the code challenge method is missing', function () {
    [$client] = oauthClient();
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'missing-method-state',
        'code_challenge' => $challenge,
    ]))
        ->assertStatus(302)
        ->assertSessionHasErrors([
            'code_challenge_method' => 'The code challenge method field is required when code challenge is present.',
        ]);

    expect(AuthorizationCode::query()->count())->toBe(0);
});

it('exchanges authorization code for tokens with valid pkce verifier', function () {
    [$client, $policy, $plainSecret] = oauthClient();
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    [$code] = issueAuthorizationCodeForOauthClient($user, $client);

    $tokenResponse = $this->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'code' => $code,
        'redirect_uri' => 'https://portal.example.com/callback',
        'code_verifier' => $verifier,
    ]);

    $tokenResponse
        ->assertOk()
        ->assertJsonPath('message', 'OAuth token issued successfully.')
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('meta', [])
        ->assertJsonPath('errors', [])
        ->assertJsonStructure([
            'message',
            'data' => [
                'token_type',
                'access_token',
                'refresh_token',
                'expires_in',
                'refresh_token_expires_in',
                'scope',
            ],
            'meta',
            'errors',
        ]);

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
        'code_hash' => hash('sha256', $code),
    ]);
    expect(Token::query()->count())->toBe(1);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.token.issued',
        'description' => 'OAuth token issued.',
    ]);

    $issueActivity = Activity::query()
        ->where('event', 'oauth.token.issued')
        ->latest()
        ->firstOrFail();

    expect($issueActivity->properties->toArray())->not->toHaveKeys(['access_token', 'refresh_token', 'authorization_code']);
});

it('rejects token exchange when pkce verifier is invalid', function () {
    [$client, , $plainSecret] = oauthClient();
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    [$code] = issueAuthorizationCodeForOauthClient($user, $client, ['state' => null]);

    $this->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'code' => $code,
        'redirect_uri' => 'https://portal.example.com/callback',
        'code_verifier' => 'wrong-verifier',
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'OAuth token request failed.')
        ->assertJsonStructure(['errors' => ['code_verifier']]);
});

it('rejects token exchange when the authorization code was issued without a PKCE challenge', function () {
    [$client, , $plainSecret] = oauthClient([
        'policy' => [
            'pkce_required' => false,
        ],
    ]);
    $user = User::factory()->create();

    [$code] = issueAuthorizationCodeForOauthClient($user, $client, [
        'state' => 'missing-pkce-state',
        'code_challenge' => null,
        'code_challenge_method' => null,
    ]);

    $this->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'code' => $code,
        'redirect_uri' => 'https://portal.example.com/callback',
        'code_verifier' => 'plain-test-verifier-123456789',
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'OAuth token request failed.')
        ->assertJsonPath('errors.code_verifier.0', 'A PKCE code challenge is required for authorization code exchange.');
});

it('rotates refresh token on refresh grant when policy requires rotation', function () {
    [$client, , $plainSecret] = oauthClient();
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    [$code] = issueAuthorizationCodeForOauthClient($user, $client, ['state' => null]);

    $firstTokenResponse = $this->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'code' => $code,
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

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.token.refreshed',
        'description' => 'OAuth token refreshed.',
    ]);
});

it('rejects replay when the same authorization code is exchanged twice', function () {
    [$client, , $plainSecret] = oauthClient();
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    [$code] = issueAuthorizationCodeForOauthClient($user, $client, ['state' => 'replay-state']);

    $this->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'code' => $code,
        'redirect_uri' => 'https://portal.example.com/callback',
        'code_verifier' => $verifier,
    ])->assertOk();

    $this->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'code' => $code,
        'redirect_uri' => 'https://portal.example.com/callback',
        'code_verifier' => $verifier,
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'OAuth token request failed.')
        ->assertJsonPath('errors.code.0', 'The authorization code is expired, revoked, or already used.');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.token.grant_failed',
        'description' => 'OAuth token grant failed.',
    ]);
});

it('rejects token exchange for an expired authorization code', function () {
    [$client, , $plainSecret] = oauthClient();
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    [$code] = issueAuthorizationCodeForOauthClient($user, $client, ['state' => 'expired-code-state']);

    AuthorizationCode::query()->update([
        'expires_at' => now()->subMinute(),
    ]);

    $this->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'code' => $code,
        'redirect_uri' => 'https://portal.example.com/callback',
        'code_verifier' => $verifier,
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'OAuth token request failed.')
        ->assertJsonPath('errors.code.0', 'The authorization code is expired, revoked, or already used.');
});

it('continues the intended authorize flow after login and renders the consent page', function () {
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

    $this->flushHeaders();

    $response = $this
        ->actingAs($user)
        ->get($loginRedirect);

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('OAuth/Consent')
            ->where('client.name', 'Portal Client')
            ->where('scopes.0.name', 'OpenID')
            ->where('scopes.1.name', 'Profile')
            ->has('consentToken'));

    expect(AuthorizationCode::query()->count())->toBe(0);
});
