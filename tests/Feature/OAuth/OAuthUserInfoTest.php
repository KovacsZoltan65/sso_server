<?php

/**
 * php artisan test tests/Feature/OAuth/OAuthUserInfoTest.php
 */

declare(strict_types=1);

use App\Models\ClientSecret;
use App\Models\AuthorizationCode;
use App\Models\Scope;
use App\Models\Token;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Services\OAuth\OidcIdTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('oidc.issuer', 'https://sso-server.test');

    Scope::factory()->create(['name' => 'OpenID', 'code' => 'openid', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Profile', 'code' => 'profile', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Email', 'code' => 'email', 'is_active' => true]);
});

function userinfoOauthClient(array $overrides = []): array
{
    $policy = TokenPolicy::query()->create(array_merge([
        'name' => 'UserInfo Policy',
        'code' => 'userinfo.policy.'.fake()->unique()->numerify('###'),
        'description' => 'User info test policy.',
        'access_token_ttl_minutes' => 60,
        'refresh_token_ttl_minutes' => 1440,
        'refresh_token_rotation_enabled' => true,
        'pkce_required' => true,
        'reuse_refresh_token_forbidden' => true,
        'is_default' => false,
        'is_active' => true,
    ], $overrides['policy'] ?? []));

    $client = \App\Models\SsoClient::factory()->create([
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

    $client->scopes()->sync(Scope::query()->whereIn('code', ['openid', 'profile', 'email'])->pluck('id')->all());

    $plainSecret = 'super-secret-value';
    ClientSecret::query()->create([
        'sso_client_id' => $client->id,
        'name' => 'Initial secret',
        'secret_hash' => Hash::make($plainSecret),
        'last_four' => 'alue',
        'is_active' => true,
    ]);

    return [$client->fresh(['redirectUris', 'scopes', 'tokenPolicy']), $policy, $plainSecret];
}

it('returns sub and profile claims for a valid bearer access token', function (): void {
    [$client, $policy] = userinfoOauthClient();
    $user = User::factory()->create([
        'name' => 'Teszt Elek',
        'email' => 'elek@example.com',
        'email_verified_at' => now(),
    ]);

    $plainAccessToken = str_repeat('a', 40);

    Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', $plainAccessToken),
        'refresh_token_hash' => hash('sha256', str_repeat('r', 40)),
        'scopes' => ['openid', 'profile'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plainAccessToken)
        ->getJson(route('oauth.userinfo'))
        ->assertOk()
        ->assertJson([
            'message' => 'User info retrieved successfully.',
            'data' => [
                'sub' => (string) $user->id,
                'name' => 'Teszt Elek',
            ],
            'meta' => [],
            'errors' => [],
        ])
        ->assertJsonMissingPath('data.email')
        ->assertJsonMissingPath('data.email_verified');
});

it('returns email claims when the access token has email scope', function (): void {
    [$client, $policy] = userinfoOauthClient();
    $user = User::factory()->create([
        'name' => 'Teszt Elek',
        'email' => 'elek@example.com',
        'email_verified_at' => now(),
    ]);

    $plainAccessToken = str_repeat('b', 40);

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
        ->assertJsonPath('data.sub', (string) $user->id)
        ->assertJsonPath('data.name', 'Teszt Elek')
        ->assertJsonPath('data.email', 'elek@example.com')
        ->assertJsonPath('data.email_verified', true);
});

it('returns only sub when the token has openid scope without optional claims scopes', function (): void {
    [$client, $policy] = userinfoOauthClient();
    $user = User::factory()->create([
        'name' => 'Teszt Elek',
        'email' => 'elek@example.com',
        'email_verified_at' => now(),
    ]);

    $plainAccessToken = str_repeat('c', 40);

    Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', $plainAccessToken),
        'refresh_token_hash' => hash('sha256', str_repeat('r', 40)),
        'scopes' => ['openid'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plainAccessToken)
        ->getJson(route('oauth.userinfo'))
        ->assertOk()
        ->assertJson([
            'data' => [
                'sub' => (string) $user->id,
            ],
        ])
        ->assertJsonMissingPath('data.name')
        ->assertJsonMissingPath('data.email')
        ->assertJsonMissingPath('data.email_verified');
});

it('rejects userinfo request without bearer token', function (): void {
    $this->getJson(route('oauth.userinfo'))
        ->assertStatus(401)
        ->assertExactJson([
            'message' => 'Authentication failed.',
            'data' => [],
            'meta' => [],
            'errors' => [
                'token' => [
                    'A valid Bearer access token is required.',
                ],
            ],
        ]);
});

it('rejects userinfo request with unknown bearer token', function (): void {
    $this->withHeader('Authorization', 'Bearer '.str_repeat('x', 40))
        ->getJson(route('oauth.userinfo'))
        ->assertStatus(401)
        ->assertExactJson([
            'message' => 'Authentication failed.',
            'data' => [],
            'meta' => [],
            'errors' => [
                'token' => [
                    'The provided access token is invalid, expired, or revoked.',
                ],
            ],
        ]);
});

it('rejects userinfo request with a revoked access token', function (): void {
    [$client, $policy] = userinfoOauthClient();
    $user = User::factory()->create();

    $plainAccessToken = str_repeat('d', 40);

    Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', $plainAccessToken),
        'refresh_token_hash' => hash('sha256', str_repeat('r', 40)),
        'scopes' => ['openid', 'profile'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
        'access_token_revoked_at' => now(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plainAccessToken)
        ->getJson(route('oauth.userinfo'))
        ->assertStatus(401)
        ->assertExactJson([
            'message' => 'Authentication failed.',
            'data' => [],
            'meta' => [],
            'errors' => [
                'token' => [
                    'The provided access token is invalid, expired, or revoked.',
                ],
            ],
        ]);
});

it('rejects userinfo request with an expired access token', function (): void {
    [$client, $policy] = userinfoOauthClient();
    $user = User::factory()->create();

    $plainAccessToken = str_repeat('e', 40);

    Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', $plainAccessToken),
        'refresh_token_hash' => hash('sha256', str_repeat('r', 40)),
        'scopes' => ['openid', 'profile'],
        'access_token_expires_at' => now()->subMinute(),
        'refresh_token_expires_at' => now()->addDay(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plainAccessToken)
        ->getJson(route('oauth.userinfo'))
        ->assertStatus(401)
        ->assertExactJson([
            'message' => 'Authentication failed.',
            'data' => [],
            'meta' => [],
            'errors' => [
                'token' => [
                    'The provided access token is invalid, expired, or revoked.',
                ],
            ],
        ]);
});

it('rejects userinfo request when the bearer token matches only a refresh token hash', function (): void {
    [$client, $policy] = userinfoOauthClient();
    $user = User::factory()->create();

    $plainRefreshToken = str_repeat('f', 40);

    Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', str_repeat('a', 40)),
        'refresh_token_hash' => hash('sha256', $plainRefreshToken),
        'scopes' => ['openid', 'profile'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plainRefreshToken)
        ->getJson(route('oauth.userinfo'))
        ->assertStatus(401)
        ->assertExactJson([
            'message' => 'Authentication failed.',
            'data' => [],
            'meta' => [],
            'errors' => [
                'token' => [
                    'The provided access token is invalid, expired, or revoked.',
                ],
            ],
        ]);
});

it('rejects userinfo request when the access token lacks openid scope', function (): void {
    [$client, $policy] = userinfoOauthClient();
    $user = User::factory()->create();

    $plainAccessToken = str_repeat('g', 40);

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

it('updates last used timestamp after successful userinfo request', function (): void {
    [$client, $policy] = userinfoOauthClient();
    $user = User::factory()->create();

    $plainAccessToken = str_repeat('h', 40);

    $token = Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', $plainAccessToken),
        'refresh_token_hash' => hash('sha256', str_repeat('r', 40)),
        'scopes' => ['openid'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
        'last_used_at' => null,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plainAccessToken)
        ->getJson(route('oauth.userinfo'))
        ->assertOk();

    expect($token->fresh()?->last_used_at)->not->toBeNull();
});

it('returns a sub claim that is consistent with the id token subject contract', function (): void {
    [$client, $policy] = userinfoOauthClient();
    $user = User::factory()->create([
        'name' => 'Teszt Elek',
        'email' => 'elek@example.com',
    ]);

    $plainAccessToken = str_repeat('z', 40);

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

    $authorizationCode = AuthorizationCode::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'code_hash' => hash('sha256', 'oidc-userinfo-code'),
        'redirect_uri' => 'https://portal.example.com/callback',
        'redirect_uri_hash' => hash('sha256', 'https://portal.example.com/callback'),
        'nonce' => 'userinfo-nonce',
        'code_challenge' => 'challenge',
        'code_challenge_method' => 'S256',
        'scopes' => ['openid', 'profile', 'email'],
        'expires_at' => now()->addMinutes(5),
    ]);

    $userinfoSubject = $this->withHeader('Authorization', 'Bearer '.$plainAccessToken)
        ->getJson(route('oauth.userinfo'))
        ->assertOk()
        ->json('data.sub');

    $idTokenClaims = app(OidcIdTokenService::class)->claimsForAuthorizationCode($authorizationCode);

    expect($userinfoSubject)->toBe($idTokenClaims['sub'] ?? null);
});
