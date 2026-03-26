<?php

/**
 * php artisan test tests/Feature/OAuth/OAuthTokenIntrospectTest.php
 */

declare(strict_types=1);

use App\Models\ClientSecret;
use App\Models\SsoClient;
use App\Models\Token;
use App\Models\TokenPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('introspects an active access token for the authenticated client', function (): void {
    $user = User::factory()->create();
    $policy = TokenPolicy::factory()->create(['is_active' => true]);

    $client = SsoClient::factory()->create([
        'client_id' => 'portal-client',
        'is_active' => true,
        'token_policy_id' => $policy->id,
    ]);

    $plainSecret = 'super-secret-value-123';
    ClientSecret::query()->create([
        'sso_client_id' => $client->id,
        'name' => 'Initial secret',
        'secret_hash' => bcrypt($plainSecret),
        'last_four' => substr($plainSecret, -4),
        'is_active' => true,
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

    $this->postJson(route('oauth.introspect'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $plainAccessToken,
        'token_type_hint' => 'access_token',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Token introspection completed.')
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.token_type', 'access_token')
        ->assertJsonPath('data.client_id', 'portal-client')
        ->assertJsonPath('data.scope', 'openid profile')
        ->assertJsonPath('data.sub', (string) $user->id)
        ->assertJson(fn ($json) => $json->whereType('data.exp', 'integer')->etc());
});

it('introspects an active refresh token for the authenticated client', function (): void {
    $user = User::factory()->create();
    $policy = TokenPolicy::factory()->create(['is_active' => true]);

    $client = SsoClient::factory()->create([
        'client_id' => 'portal-client',
        'is_active' => true,
        'token_policy_id' => $policy->id,
    ]);

    $plainSecret = 'super-secret-value-123';
    ClientSecret::query()->create([
        'sso_client_id' => $client->id,
        'name' => 'Initial secret',
        'secret_hash' => bcrypt($plainSecret),
        'last_four' => substr($plainSecret, -4),
        'is_active' => true,
    ]);

    $plainRefreshToken = str_repeat('b', 40);

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

    $this->postJson(route('oauth.introspect'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $plainRefreshToken,
        'token_type_hint' => 'refresh_token',
    ])
        ->assertOk()
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.token_type', 'refresh_token')
        ->assertJsonPath('data.client_id', 'portal-client')
        ->assertJsonPath('data.scope', 'openid profile')
        ->assertJsonPath('data.sub', (string) $user->id)
        ->assertJson(fn ($json) => $json->whereType('data.exp', 'integer')->etc());
});

it('returns inactive when token does not exist', function (): void {
    $policy = TokenPolicy::factory()->create(['is_active' => true]);

    $client = SsoClient::factory()->create([
        'client_id' => 'portal-client',
        'is_active' => true,
        'token_policy_id' => $policy->id,
    ]);

    $plainSecret = 'super-secret-value-123';
    ClientSecret::query()->create([
        'sso_client_id' => $client->id,
        'name' => 'Initial secret',
        'secret_hash' => bcrypt($plainSecret),
        'last_four' => substr($plainSecret, -4),
        'is_active' => true,
    ]);

    $this->postJson(route('oauth.introspect'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => str_repeat('x', 40),
        'token_type_hint' => 'access_token',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Token introspection completed.')
        ->assertExactJson([
            'message' => 'Token introspection completed.',
            'data' => [
                'active' => false,
            ],
            'meta' => [],
            'errors' => [],
        ]);
});

it('returns inactive for a revoked access token', function (): void {
    $user = User::factory()->create();
    $policy = TokenPolicy::factory()->create(['is_active' => true]);

    $client = SsoClient::factory()->create([
        'client_id' => 'portal-client',
        'is_active' => true,
        'token_policy_id' => $policy->id,
    ]);

    $plainSecret = 'super-secret-value-123';
    ClientSecret::query()->create([
        'sso_client_id' => $client->id,
        'name' => 'Initial secret',
        'secret_hash' => bcrypt($plainSecret),
        'last_four' => substr($plainSecret, -4),
        'is_active' => true,
    ]);

    $plainAccessToken = str_repeat('a', 40);

    Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', $plainAccessToken),
        'refresh_token_hash' => hash('sha256', str_repeat('r', 40)),
        'scopes' => ['openid'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
        'access_token_revoked_at' => now(),
    ]);

    $this->postJson(route('oauth.introspect'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $plainAccessToken,
        'token_type_hint' => 'access_token',
    ])
        ->assertOk()
        ->assertJsonPath('data.active', false);
});

it('returns inactive for an expired access token', function (): void {
    $user = User::factory()->create();
    $policy = TokenPolicy::factory()->create(['is_active' => true]);

    $client = SsoClient::factory()->create([
        'client_id' => 'portal-client',
        'is_active' => true,
        'token_policy_id' => $policy->id,
    ]);

    $plainSecret = 'super-secret-value-123';
    ClientSecret::query()->create([
        'sso_client_id' => $client->id,
        'name' => 'Initial secret',
        'secret_hash' => bcrypt($plainSecret),
        'last_four' => substr($plainSecret, -4),
        'is_active' => true,
    ]);

    $plainAccessToken = str_repeat('a', 40);

    Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', $plainAccessToken),
        'refresh_token_hash' => hash('sha256', str_repeat('r', 40)),
        'scopes' => ['openid'],
        'access_token_expires_at' => now()->subMinute(),
        'refresh_token_expires_at' => now()->addDay(),
    ]);

    $this->postJson(route('oauth.introspect'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $plainAccessToken,
        'token_type_hint' => 'access_token',
    ])
        ->assertOk()
        ->assertJsonPath('data.active', false);
});

it('does not allow a client to introspect another clients token', function (): void {
    $user = User::factory()->create();
    $policy = TokenPolicy::factory()->create(['is_active' => true]);

    $clientA = SsoClient::factory()->create([
        'client_id' => 'portal-client-a',
        'is_active' => true,
        'token_policy_id' => $policy->id,
    ]);

    $clientB = SsoClient::factory()->create([
        'client_id' => 'portal-client-b',
        'is_active' => true,
        'token_policy_id' => $policy->id,
    ]);

    $plainSecretA = 'client-a-secret-123';
    ClientSecret::query()->create([
        'sso_client_id' => $clientA->id,
        'name' => 'Secret A',
        'secret_hash' => bcrypt($plainSecretA),
        'last_four' => substr($plainSecretA, -4),
        'is_active' => true,
    ]);

    $plainAccessTokenB = str_repeat('z', 40);

    Token::query()->create([
        'sso_client_id' => $clientB->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', $plainAccessTokenB),
        'refresh_token_hash' => hash('sha256', str_repeat('y', 40)),
        'scopes' => ['openid'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
    ]);

    $this->postJson(route('oauth.introspect'), [
        'client_id' => $clientA->client_id,
        'client_secret' => $plainSecretA,
        'token' => $plainAccessTokenB,
        'token_type_hint' => 'access_token',
    ])
        ->assertOk()
        ->assertJsonPath('data.active', false);
});

it('rejects introspection with invalid client credentials', function (): void {
    $policy = TokenPolicy::factory()->create(['is_active' => true]);

    $client = SsoClient::factory()->create([
        'client_id' => 'portal-client',
        'is_active' => true,
        'token_policy_id' => $policy->id,
    ]);

    $plainSecret = 'super-secret-value-123';
    ClientSecret::query()->create([
        'sso_client_id' => $client->id,
        'name' => 'Initial secret',
        'secret_hash' => bcrypt($plainSecret),
        'last_four' => substr($plainSecret, -4),
        'is_active' => true,
    ]);

    $this->postJson(route('oauth.introspect'), [
        'client_id' => $client->client_id,
        'client_secret' => 'wrong-secret',
        'token' => str_repeat('x', 40),
        'token_type_hint' => 'access_token',
    ])
        ->assertStatus(422);
});

it('resolves an access token without a type hint', function (): void {
    $user = User::factory()->create();
    $policy = TokenPolicy::factory()->create(['is_active' => true]);

    $client = SsoClient::factory()->create([
        'client_id' => 'portal-client',
        'is_active' => true,
        'token_policy_id' => $policy->id,
    ]);

    $plainSecret = 'super-secret-value-123';
    ClientSecret::query()->create([
        'sso_client_id' => $client->id,
        'name' => 'Initial secret',
        'secret_hash' => bcrypt($plainSecret),
        'last_four' => substr($plainSecret, -4),
        'is_active' => true,
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

    $this->postJson(route('oauth.introspect'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $plainAccessToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.token_type', 'access_token');
});

it('returns inactive for a revoked refresh token', function (): void {
    $user = User::factory()->create();
    $policy = TokenPolicy::factory()->create(['is_active' => true]);

    $client = SsoClient::factory()->create([
        'client_id' => 'portal-client',
        'is_active' => true,
        'token_policy_id' => $policy->id,
    ]);

    $plainSecret = 'super-secret-value-123';
    ClientSecret::query()->create([
        'sso_client_id' => $client->id,
        'name' => 'Initial secret',
        'secret_hash' => bcrypt($plainSecret),
        'last_four' => substr($plainSecret, -4),
        'is_active' => true,
    ]);

    $plainRefreshToken = str_repeat('b', 40);

    Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', str_repeat('a', 40)),
        'refresh_token_hash' => hash('sha256', $plainRefreshToken),
        'scopes' => ['openid'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
        'refresh_token_revoked_at' => now(),
    ]);

    $this->postJson(route('oauth.introspect'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $plainRefreshToken,
        'token_type_hint' => 'refresh_token',
    ])
        ->assertOk()
        ->assertJsonPath('data.active', false);
});

it('returns inactive for an expired refresh token', function (): void {
    $user = User::factory()->create();
    $policy = TokenPolicy::factory()->create(['is_active' => true]);

    $client = SsoClient::factory()->create([
        'client_id' => 'portal-client',
        'is_active' => true,
        'token_policy_id' => $policy->id,
    ]);

    $plainSecret = 'super-secret-value-123';
    ClientSecret::query()->create([
        'sso_client_id' => $client->id,
        'name' => 'Initial secret',
        'secret_hash' => bcrypt($plainSecret),
        'last_four' => substr($plainSecret, -4),
        'is_active' => true,
    ]);

    $plainRefreshToken = str_repeat('b', 40);

    Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', str_repeat('a', 40)),
        'refresh_token_hash' => hash('sha256', $plainRefreshToken),
        'scopes' => ['openid'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->subMinute(),
    ]);

    $this->postJson(route('oauth.introspect'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $plainRefreshToken,
        'token_type_hint' => 'refresh_token',
    ])
        ->assertOk()
        ->assertJsonPath('data.active', false);
});
