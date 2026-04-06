<?php

declare(strict_types=1);

use App\Models\AuthorizationCode;
use App\Models\Scope;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Services\OAuth\OidcIdTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('oidc.issuer', 'https://sso-server.test');
    config()->set('oidc.signing.alg', 'RS256');
    config()->set('oidc.signing.kid', 'logout-test-key-1');
    config()->set('oidc.signing.private_key_path', base_path('tests/Fixtures/oidc/private.pem'));
    config()->set('oidc.signing.public_key_path', base_path('tests/Fixtures/oidc/public.pem'));

    Scope::factory()->create(['name' => 'OpenID', 'code' => 'openid', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Profile', 'code' => 'profile', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Email', 'code' => 'email', 'is_active' => true]);
});

function logoutOauthClient(): array
{
    $policy = TokenPolicy::query()->create([
        'name' => 'Logout Policy',
        'code' => 'logout.policy.'.fake()->unique()->numerify('###'),
        'description' => 'Logout test policy.',
        'access_token_ttl_minutes' => 60,
        'refresh_token_ttl_minutes' => 1440,
        'refresh_token_rotation_enabled' => true,
        'pkce_required' => true,
        'reuse_refresh_token_forbidden' => true,
        'is_default' => false,
        'is_active' => true,
    ]);

    $client = \App\Models\SsoClient::factory()->create([
        'name' => 'Portal Client',
        'client_id' => 'portal-client-'.fake()->unique()->numerify('###'),
        'token_policy_id' => $policy->id,
        'is_active' => true,
    ]);

    $client->redirectUris()->create([
        'uri' => 'http://sso-client.test/auth/sso/callback',
        'uri_hash' => hash('sha256', 'http://sso-client.test/auth/sso/callback'),
        'is_primary' => true,
    ]);

    $client->redirectUris()->create([
        'uri' => 'http://sso-client.test/auth/logout/return',
        'uri_hash' => hash('sha256', 'http://sso-client.test/auth/logout/return'),
        'is_primary' => false,
    ]);

    $client->scopes()->sync(Scope::query()->whereIn('code', ['openid', 'profile', 'email'])->pluck('id')->all());

    return [$client->fresh(['redirectUris', 'scopes', 'tokenPolicy']), $policy];
}

function logoutIdTokenHint(\App\Models\SsoClient $client, User $user, TokenPolicy $policy): string
{
    $authorizationCode = AuthorizationCode::query()->create([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'code_hash' => hash('sha256', fake()->uuid()),
        'redirect_uri' => 'http://sso-client.test/auth/sso/callback',
        'redirect_uri_hash' => hash('sha256', 'http://sso-client.test/auth/sso/callback'),
        'nonce' => 'logout-nonce',
        'code_challenge' => 'challenge',
        'code_challenge_method' => 'S256',
        'scopes' => ['openid', 'profile', 'email'],
        'expires_at' => now()->addMinutes(5),
    ]);

    return app(OidcIdTokenService::class)->issueForAuthorizationCode($authorizationCode);
}

it('logs out the provider session and redirects to a valid post logout redirect uri', function (): void {
    [$client, $policy] = logoutOauthClient();
    $user = User::factory()->create();
    $idTokenHint = logoutIdTokenHint($client, $user, $policy);

    $response = $this->actingAs($user)->get(route('oidc.end_session', [
        'id_token_hint' => $idTokenHint,
        'post_logout_redirect_uri' => 'http://sso-client.test/auth/logout/return',
        'state' => 'logout-state-123',
    ]));

    $response->assertRedirect('http://sso-client.test/auth/logout/return?state=logout-state-123');
    $this->assertGuest();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.end_session.requested',
        'description' => 'OIDC end session requested.',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.end_session.completed',
        'description' => 'OIDC end session completed.',
    ]);
});

it('does not allow open redirects when the logout redirect is not registered for the hinted client', function (): void {
    [$client, $policy] = logoutOauthClient();
    $user = User::factory()->create();
    $idTokenHint = logoutIdTokenHint($client, $user, $policy);

    $response = $this->actingAs($user)->get(route('oidc.end_session', [
        'id_token_hint' => $idTokenHint,
        'post_logout_redirect_uri' => 'https://evil.example.com/logout',
        'state' => 'logout-state-456',
    ]));

    $response
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Sikeres kijelentkezes.');

    $this->assertGuest();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.end_session.redirect_denied',
        'description' => 'OIDC end session redirect denied.',
    ]);
});

it('falls back cleanly when no post logout redirect uri is provided', function (): void {
    [$client, $policy] = logoutOauthClient();
    $user = User::factory()->create();
    $idTokenHint = logoutIdTokenHint($client, $user, $policy);

    $response = $this->actingAs($user)->get(route('oidc.end_session', [
        'id_token_hint' => $idTokenHint,
    ]));

    $response
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Sikeres kijelentkezes.');

    $this->assertGuest();
});

it('still completes logout when the id token hint is malformed', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('oidc.end_session', [
        'id_token_hint' => 'malformed-token',
        'post_logout_redirect_uri' => 'https://evil.example.com/logout',
    ]));

    $response
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Sikeres kijelentkezes.');

    $this->assertGuest();
});
