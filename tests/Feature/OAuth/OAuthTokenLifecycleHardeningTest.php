<?php

declare(strict_types=1);

use App\Models\AuthorizationCode;
use App\Models\ClientSecret;
use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\Token;
use App\Models\TokenPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();

    Scope::factory()->create(['name' => 'OpenID', 'code' => 'openid', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Profile', 'code' => 'profile', 'is_active' => true]);
});

function strictOauthClient(array $policyOverrides = []): array
{
    $policy = TokenPolicy::query()->create(array_merge([
        'name' => 'Strict Rotation Policy',
        'code' => 'strict.rotation.'.fake()->unique()->numerify('###'),
        'description' => 'Refresh rotation and reuse detection.',
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
        'client_id' => 'portal-client-'.fake()->unique()->numerify('###'),
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
    ClientSecret::query()->create([
        'sso_client_id' => $client->id,
        'name' => 'Initial secret',
        'secret_hash' => Hash::make($plainSecret),
        'last_four' => 'alue',
        'is_active' => true,
    ]);

    return [$client->fresh(['redirectUris', 'scopes', 'tokenPolicy']), $policy, $plainSecret];
}

function issueAuthorizationCodeTokenPair(SsoClient $client, string $plainSecret, User $user): array
{
    $verifier = 'plain-test-verifier-123456789';
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $authorize = test()->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'token-lifecycle-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    parse_str(parse_url($authorize->headers->get('Location'), PHP_URL_QUERY) ?: '', $query);

    $response = test()->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'code' => $query['code'],
        'redirect_uri' => 'https://portal.example.com/callback',
        'code_verifier' => $verifier,
    ])->assertOk();

    return $response->json('data');
}

it('stores family metadata and audits both access and refresh token issuance', function (): void {
    [$client, $policy, $plainSecret] = strictOauthClient();
    $user = User::factory()->create();

    $issued = issueAuthorizationCodeTokenPair($client, $plainSecret, $user);

    $token = Token::query()
        ->where('access_token_hash', hash('sha256', $issued['access_token']))
        ->firstOrFail();

    expect($token->token_policy_id)->toBe($policy->id)
        ->and($token->family_id)->not->toBeNull()
        ->and($token->parent_token_id)->toBeNull()
        ->and($token->meta)->toMatchArray(['issued_via' => 'authorization_code']);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.access_token.issued',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.refresh_token.issued',
    ]);
});

it('rotates a refresh token into a new token pair and links the chain', function (): void {
    [$client, , $plainSecret] = strictOauthClient();
    $user = User::factory()->create();

    $firstPair = issueAuthorizationCodeTokenPair($client, $plainSecret, $user);

    $refreshResponse = $this->postJson(route('oauth.token'), [
        'grant_type' => 'refresh_token',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'refresh_token' => $firstPair['refresh_token'],
    ])->assertOk();

    $secondPair = $refreshResponse->json('data');

    $originalToken = Token::query()->where('refresh_token_hash', hash('sha256', $firstPair['refresh_token']))->firstOrFail();
    $replacementToken = Token::query()->where('refresh_token_hash', hash('sha256', $secondPair['refresh_token']))->firstOrFail();

    expect($secondPair['access_token'])->not->toBe($firstPair['access_token'])
        ->and($secondPair['refresh_token'])->not->toBe($firstPair['refresh_token'])
        ->and($originalToken->refresh_token_used_at)->not->toBeNull()
        ->and($originalToken->refresh_token_revoked_at)->not->toBeNull()
        ->and($originalToken->refresh_token_revoked_reason)->toBe('rotated')
        ->and($originalToken->replaced_by_token_id)->toBe($replacementToken->id)
        ->and($replacementToken->parent_token_id)->toBe($originalToken->id)
        ->and($replacementToken->family_id)->toBe($originalToken->family_id);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.refresh_token.rotated',
    ]);
});

it('detects refresh token reuse, revokes the family, and audits the incident', function (): void {
    [$client, , $plainSecret] = strictOauthClient();
    $user = User::factory()->create();

    $firstPair = issueAuthorizationCodeTokenPair($client, $plainSecret, $user);

    $secondPair = $this->postJson(route('oauth.token'), [
        'grant_type' => 'refresh_token',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'refresh_token' => $firstPair['refresh_token'],
    ])->assertOk()->json('data');

    $this->postJson(route('oauth.token'), [
        'grant_type' => 'refresh_token',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'refresh_token' => $firstPair['refresh_token'],
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'OAuth token request failed.')
        ->assertJsonPath('errors.refresh_token.0', 'The refresh token is invalid.');

    $oldToken = Token::query()->where('refresh_token_hash', hash('sha256', $firstPair['refresh_token']))->firstOrFail();
    $newToken = Token::query()->where('refresh_token_hash', hash('sha256', $secondPair['refresh_token']))->firstOrFail();

    expect($oldToken->refresh_token_reuse_detected_at)->not->toBeNull()
        ->and($newToken->refresh_token_revoked_reason)->toBe('family_reuse_detected')
        ->and($newToken->access_token_revoked_reason)->toBe('family_reuse_detected');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.refresh_token.reuse_detected',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.token.family_revoked',
    ]);
});

it('revoked refresh tokens cannot be used to mint a new token pair', function (): void {
    [$client, , $plainSecret] = strictOauthClient();
    $user = User::factory()->create();

    $firstPair = issueAuthorizationCodeTokenPair($client, $plainSecret, $user);

    $this->postJson(route('oauth.revoke'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $firstPair['refresh_token'],
        'token_type_hint' => 'refresh_token',
        'reason' => 'manual_revoke',
    ])->assertOk();

    $this->postJson(route('oauth.token'), [
        'grant_type' => 'refresh_token',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'refresh_token' => $firstPair['refresh_token'],
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.refresh_token.0', 'The refresh token is invalid.');
});

it('revoked access tokens introspect as inactive and preserve revoke reason', function (): void {
    [$client, , $plainSecret] = strictOauthClient();
    $user = User::factory()->create();

    $issued = issueAuthorizationCodeTokenPair($client, $plainSecret, $user);

    $this->postJson(route('oauth.revoke'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $issued['access_token'],
        'token_type_hint' => 'access_token',
        'reason' => 'security_event',
    ])->assertOk();

    $token = Token::query()->where('access_token_hash', hash('sha256', $issued['access_token']))->firstOrFail();

    expect($token->access_token_revoked_reason)->toBe('security_event');

    $this->postJson(route('oauth.introspect'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $issued['access_token'],
        'token_type_hint' => 'access_token',
    ])
        ->assertOk()
        ->assertJsonPath('data.active', false);
});

it('repeated revoke remains idempotent for already revoked tokens', function (): void {
    [$client, , $plainSecret] = strictOauthClient();
    $user = User::factory()->create();

    $issued = issueAuthorizationCodeTokenPair($client, $plainSecret, $user);

    $this->postJson(route('oauth.revoke'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $issued['access_token'],
        'token_type_hint' => 'access_token',
    ])->assertOk();

    $this->postJson(route('oauth.revoke'), [
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'token' => $issued['access_token'],
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

it('audits token refresh denial without logging plain token values', function (): void {
    [$client, , $plainSecret] = strictOauthClient();
    $user = User::factory()->create();

    $issued = issueAuthorizationCodeTokenPair($client, $plainSecret, $user);

    Token::query()->where('refresh_token_hash', hash('sha256', $issued['refresh_token']))->update([
        'refresh_token_expires_at' => now()->subMinute(),
    ]);

    $this->postJson(route('oauth.token'), [
        'grant_type' => 'refresh_token',
        'client_id' => $client->client_id,
        'client_secret' => $plainSecret,
        'refresh_token' => $issued['refresh_token'],
    ])->assertStatus(422);

    $failureActivity = Activity::query()
        ->where('event', 'oauth.token.grant_failed')
        ->latest()
        ->firstOrFail();

    expect($failureActivity->properties->toArray())->not->toHaveKeys(['refresh_token', 'access_token'])
        ->and($failureActivity->properties['reason'])->toBe('refresh_token_inactive');
});
