<?php

/**
 * php artisan test tests/Feature/OAuth/OAuthTokenRevokeTest.php
 */

declare(strict_types=1);

use App\Models\ClientSecret;
use App\Models\SsoClient;
use App\Models\Token;
use App\Models\TokenPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('en');
    config()->set('app.locale', 'en');
});

it('revokes an active access token for the authenticated client', function (): void {
    $user = User::factory()->create();

    $policy = TokenPolicy::factory()->create([
        'is_active' => true,
    ]);

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

    $token = Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', $plainAccessToken),
        'refresh_token_hash' => hash('sha256', str_repeat('r', 40)),
        'scopes' => ['openid', 'profile'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
    ]);

    $this->postJson(route('oauth.revoke'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $plainAccessToken,
        'token_type_hint' => 'access_token',
    ])
        ->assertOk()
        ->assertExactJson([
            'message' => 'Token revoked successfully.',
            'data' => null,
            'meta' => [],
            'errors' => [],
        ]);

    expect($token->fresh()?->access_token_revoked_at)->not->toBeNull();
    expect($token->fresh()?->refresh_token_revoked_at)->toBeNull();
    expect($token->fresh()?->family_revoked_at)->toBeNull();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.token.revoked',
        'description' => 'OAuth token revoked.',
    ]);

    $revokeActivity = Activity::query()
        ->where('event', 'oauth.token.revoked')
        ->latest()
        ->firstOrFail();

    expect($revokeActivity->properties->toArray())->not->toHaveKeys(['token', 'access_token', 'refresh_token']);
});

it('revokes an active refresh token for the authenticated client', function (): void {
    $user = User::factory()->create();

    $policy = TokenPolicy::factory()->create([
        'is_active' => true,
    ]);

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

    $token = Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', str_repeat('a', 40)),
        'refresh_token_hash' => hash('sha256', $plainRefreshToken),
        'scopes' => ['openid'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
    ]);

    $this->postJson(route('oauth.revoke'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $plainRefreshToken,
        'token_type_hint' => 'refresh_token',
    ])
        ->assertOk()
        ->assertExactJson([
            'message' => 'Token revoked successfully.',
            'data' => null,
            'meta' => [],
            'errors' => [],
        ]);

    expect($token->fresh()?->refresh_token_revoked_at)->not->toBeNull();
    expect($token->fresh()?->access_token_revoked_at)->toBeNull();
    expect($token->fresh()?->family_revoked_at)->toBeNull();
});

it('returns success even when token does not exist', function (): void {
    $policy = TokenPolicy::factory()->create([
        'is_active' => true,
    ]);

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

    $this->postJson(route('oauth.revoke'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => str_repeat('x', 40),
        'token_type_hint' => 'access_token',
    ])
        ->assertOk()
        ->assertExactJson([
            'message' => 'Token revoked successfully.',
            'data' => null,
            'meta' => [],
            'errors' => [],
        ]);
});

it('does not revoke another clients token', function (): void {
    $user = User::factory()->create();

    $policy = TokenPolicy::factory()->create([
        'is_active' => true,
    ]);

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

    $tokenB = Token::query()->create([
        'sso_client_id' => $clientB->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', $plainAccessTokenB),
        'refresh_token_hash' => hash('sha256', str_repeat('y', 40)),
        'scopes' => ['openid'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
    ]);

    $this->postJson(route('oauth.revoke'), [
        'client_id' => $clientA->client_id,
        'client_secret' => $plainSecretA,
        'token' => $plainAccessTokenB,
        'token_type_hint' => 'access_token',
    ])->assertOk();

    expect($tokenB->fresh()?->access_token_revoked_at)->toBeNull();
});

it('keeps revocation idempotent for an already revoked access token', function (): void {
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

    $token = Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', $plainAccessToken),
        'refresh_token_hash' => hash('sha256', str_repeat('r', 40)),
        'scopes' => ['openid'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
    ]);

    $this->postJson(route('oauth.revoke'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $plainAccessToken,
        'token_type_hint' => 'access_token',
    ])->assertOk();

    $firstRevokedAt = $token->fresh()?->access_token_revoked_at?->toIso8601String();
    $auditCount = Activity::query()->where('event', 'oauth.token.revoked')->count();

    $this->postJson(route('oauth.revoke'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $plainAccessToken,
        'token_type_hint' => 'access_token',
    ])->assertOk();

    expect($token->fresh()?->access_token_revoked_at?->toIso8601String())->toBe($firstRevokedAt)
        ->and(Activity::query()->where('event', 'oauth.token.revoked')->count())->toBe($auditCount);
});

it('returns inactive after revocation and does not revoke the refresh token family', function (): void {
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
    $plainRefreshToken = str_repeat('b', 40);

    $token = Token::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'family_id' => fake()->uuid(),
        'access_token_hash' => hash('sha256', $plainAccessToken),
        'refresh_token_hash' => hash('sha256', $plainRefreshToken),
        'scopes' => ['openid'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
    ]);

    $this->postJson(route('oauth.revoke'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $plainAccessToken,
        'token_type_hint' => 'access_token',
    ])->assertOk();

    $this->postJson(route('oauth.introspect'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $plainAccessToken,
        'token_type_hint' => 'access_token',
    ])
        ->assertOk()
        ->assertJsonPath('data.active', false);

    $this->postJson(route('oauth.introspect'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $plainRefreshToken,
        'token_type_hint' => 'refresh_token',
    ])
        ->assertOk()
        ->assertJsonPath('data.active', true);

    expect($token->fresh()?->refresh_token_revoked_at)->toBeNull()
        ->and($token->fresh()?->family_revoked_at)->toBeNull();
});

it('does not let another client revoke a refresh token', function (): void {
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

    $plainRefreshTokenB = str_repeat('z', 40);

    $tokenB = Token::query()->create([
        'sso_client_id' => $clientB->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'access_token_hash' => hash('sha256', str_repeat('y', 40)),
        'refresh_token_hash' => hash('sha256', $plainRefreshTokenB),
        'scopes' => ['openid'],
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
    ]);

    $this->postJson(route('oauth.revoke'), [
        'client_id' => $clientA->client_id,
        'client_secret' => $plainSecretA,
        'token' => $plainRefreshTokenB,
        'token_type_hint' => 'refresh_token',
    ])->assertOk();

    expect($tokenB->fresh()?->refresh_token_revoked_at)->toBeNull()
        ->and($tokenB->fresh()?->family_revoked_at)->toBeNull();
});

it('rejects revoke request with invalid client credentials', function (): void {
    $policy = TokenPolicy::factory()->create([
        'is_active' => true,
    ]);

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

    $this->postJson(route('oauth.revoke'), [
        'client_id' => $client->client_id,
        'client_secret' => 'wrong-secret',
        'token' => str_repeat('x', 40),
        'token_type_hint' => 'access_token',
    ])
        ->assertUnauthorized()
        ->assertExactJson([
            'message' => 'Invalid client credentials.',
            'data' => [],
            'meta' => [],
            'errors' => [
                'client' => [
                    'Invalid client credentials.',
                ],
            ],
        ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.client_auth.failed',
        'description' => 'OAuth client authentication failed.',
    ]);
});
