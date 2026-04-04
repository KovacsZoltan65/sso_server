<?php

declare(strict_types=1);

use App\Models\ClientSecret;
use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\Token;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Services\OAuth\OAuthAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();

    Scope::factory()->create(['name' => 'OpenID', 'code' => 'openid', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Profile', 'code' => 'profile', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Email', 'code' => 'email', 'is_active' => true]);
});

function integrationContractClient(array $scopeCodes = ['openid', 'profile', 'email']): array
{
    $policy = TokenPolicy::query()->create([
        'name' => 'Integration Contract Policy',
        'code' => 'integration.contract.'.fake()->unique()->numerify('###'),
        'description' => 'OAuth integration contract verification policy.',
        'access_token_ttl_minutes' => 60,
        'refresh_token_ttl_minutes' => 1440,
        'refresh_token_rotation_enabled' => true,
        'pkce_required' => true,
        'reuse_refresh_token_forbidden' => true,
        'is_default' => false,
        'is_active' => true,
    ]);

    $client = SsoClient::factory()->create([
        'name' => 'Portal Client',
        'client_id' => 'portal-client-'.fake()->unique()->numerify('###'),
        'token_policy_id' => $policy->id,
        'is_active' => true,
    ]);

    $redirectUri = 'https://portal.example.com/auth/sso/callback';

    $client->redirectUris()->create([
        'uri' => $redirectUri,
        'uri_hash' => hash('sha256', $redirectUri),
        'is_primary' => true,
    ]);

    $client->scopes()->sync(
        Scope::query()->whereIn('code', $scopeCodes)->pluck('id')->all(),
    );

    $plainSecret = 'super-secret-value';

    ClientSecret::query()->create([
        'sso_client_id' => $client->id,
        'name' => 'Initial secret',
        'secret_hash' => Hash::make($plainSecret),
        'last_four' => 'alue',
        'is_active' => true,
    ]);

    return [$client->fresh(['redirectUris', 'scopes', 'tokenPolicy']), $policy, $plainSecret, $redirectUri];
}

function issueIntegrationAuthorizationCode(User $user, SsoClient $client, string $redirectUri): array
{
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $result = app(OAuthAuthorizationService::class)->approve($user, [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => $redirectUri,
        'scope' => 'openid profile email',
        'state' => 'contract-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]);

    return [$result['code'], $verifier];
}

it('documents the authorize consent render contract', function (): void {
    [$client, , , $redirectUri] = integrationContractClient();
    $user = User::factory()->create();
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $response = $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => $redirectUri,
        'scope' => 'openid profile email',
        'state' => 'expected-state-value',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('OAuth/Consent')
            ->where('client.name', 'Portal Client')
            ->where('scopes.0.name', 'OpenID')
            ->where('scopes.1.name', 'Profile')
            ->where('scopes.2.name', 'Email')
            ->has('consentToken'));
});

it('documents the token success json contract', function (): void {
    [$client, $policy, $plainSecret, $redirectUri] = integrationContractClient();
    $user = User::factory()->create();
    [$code, $verifier] = issueIntegrationAuthorizationCode($user, $client, $redirectUri);

    $response = $this->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'code' => $code,
        'redirect_uri' => $redirectUri,
        'code_verifier' => $verifier,
    ]);

    $response
        ->assertOk()
        ->assertExactJsonStructure([
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
        ])
        ->assertJson([
            'message' => 'OAuth token issued successfully.',
            'meta' => [],
            'errors' => [],
            'data' => [
                'token_type' => 'Bearer',
                'scope' => 'openid profile email',
            ],
        ]);

    $data = $response->json('data');

    expect($data['access_token'])->not->toBeEmpty()
        ->and($data['refresh_token'])->not->toBeEmpty()
        ->and($data['expires_in'])->toBeInt()
        ->and($data['refresh_token_expires_in'])->toBeInt();

    $this->assertDatabaseHas('tokens', [
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', $data['access_token']),
    ]);
});

it('documents the token error json contract', function (): void {
    [$client, , $plainSecret, $redirectUri] = integrationContractClient();

    $this->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'code' => 'missing-code',
        'redirect_uri' => $redirectUri,
        'code_verifier' => 'plain-test-verifier-123456789',
    ])
        ->assertStatus(422)
        ->assertExactJson([
            'message' => 'OAuth token request failed.',
            'data' => [],
            'meta' => [],
            'errors' => [
                'code' => [
                    'The provided authorization code is invalid.',
                ],
            ],
        ]);
});

it('documents the userinfo success json contract for the email-mapped client flow', function (): void {
    [$client, $policy] = integrationContractClient();
    $user = User::factory()->create([
        'name' => 'SSO Contract User',
        'email' => 'contract.user@example.test',
        'email_verified_at' => now(),
    ]);

    $plainAccessToken = str_repeat('u', 40);

    Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', $plainAccessToken),
        'refresh_token_hash' => hash('sha256', str_repeat('r', 40)),
        'scopes' => ['openid', 'profile', 'email'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plainAccessToken)
        ->getJson(route('oauth.userinfo'))
        ->assertOk()
        ->assertExactJson([
            'message' => 'User info retrieved successfully.',
            'data' => [
                'sub' => (string) $user->id,
                'name' => 'SSO Contract User',
                'email' => 'contract.user@example.test',
                'email_verified' => true,
            ],
            'meta' => [],
            'errors' => [],
        ]);
});

it('documents the userinfo forbidden json contract when openid scope is missing', function (): void {
    [$client, $policy] = integrationContractClient(['profile', 'email']);
    $user = User::factory()->create([
        'name' => 'No OpenID User',
        'email' => 'no-openid@example.test',
    ]);

    $plainAccessToken = str_repeat('n', 40);

    Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', $plainAccessToken),
        'refresh_token_hash' => hash('sha256', str_repeat('r', 40)),
        'scopes' => ['profile', 'email'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plainAccessToken)
        ->getJson(route('oauth.userinfo'))
        ->assertStatus(403)
        ->assertExactJson([
            'message' => 'User info request forbidden.',
            'data' => [],
            'meta' => [],
            'errors' => [
                'scope' => [
                    'The access token does not grant the openid scope.',
                ],
            ],
        ]);
});
