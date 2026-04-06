<?php

declare(strict_types=1);

use App\Models\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();

    config()->set('oidc.issuer', 'https://sso-server.test');
    config()->set('oidc.signing.alg', 'RS256');
    config()->set('oidc.signing.kid', 'discovery-test-key-1');
    config()->set('oidc.signing.private_key_path', base_path('tests/Fixtures/oidc/private.pem'));
    config()->set('oidc.signing.public_key_path', base_path('tests/Fixtures/oidc/public.pem'));

    Scope::factory()->create(['name' => 'OpenID', 'code' => 'openid', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Profile', 'code' => 'profile', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Email', 'code' => 'email', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Offline Access', 'code' => 'offline_access', 'is_active' => false]);
});

it('serves openid provider discovery metadata', function (): void {
    $response = getJson('/.well-known/openid-configuration');

    $response
        ->assertOk()
        ->assertJsonPath('issuer', 'https://sso-server.test')
        ->assertJsonPath('authorization_endpoint', 'https://sso-server.test/oauth/authorize')
        ->assertJsonPath('token_endpoint', 'https://sso-server.test/api/oauth/token')
        ->assertJsonPath('userinfo_endpoint', 'https://sso-server.test/api/oauth/userinfo')
        ->assertJsonPath('end_session_endpoint', 'https://sso-server.test/oidc/logout')
        ->assertJsonPath('jwks_uri', 'https://sso-server.test/.well-known/jwks.json')
        ->assertJsonPath('response_types_supported.0', 'code')
        ->assertJsonPath('grant_types_supported.0', 'authorization_code')
        ->assertJsonPath('grant_types_supported.1', 'refresh_token')
        ->assertJsonPath('subject_types_supported.0', 'public')
        ->assertJsonPath('id_token_signing_alg_values_supported.0', 'RS256')
        ->assertJsonPath('code_challenge_methods_supported.0', 'S256');

    expect($response->json('scopes_supported'))
        ->toBe(['email', 'openid', 'profile']);

    expect($response->json('claims_supported'))
        ->toBe(['sub', 'name', 'email', 'email_verified']);

    $response->assertJsonMissingPath('registration_endpoint');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.discovery.served',
        'description' => 'OIDC discovery metadata served.',
    ]);
});

it('keeps discovery metadata urls consistent with the configured issuer and jwks route', function (): void {
    $response = getJson('/.well-known/openid-configuration');
    $payload = $response->json();
    $jwksPath = route('oidc.jwks', absolute: false);
    $userinfoPath = route('oauth.userinfo', absolute: false);
    $endSessionPath = route('oidc.end_session', absolute: false);

    expect($payload)->toBeArray()
        ->and($payload['issuer'] ?? null)->toBe('https://sso-server.test')
        ->and($payload['jwks_uri'] ?? null)->toBe('https://sso-server.test/.well-known/jwks.json')
        ->and($payload['authorization_endpoint'] ?? null)->toStartWith('https://sso-server.test/')
        ->and($payload['token_endpoint'] ?? null)->toStartWith('https://sso-server.test/')
        ->and(parse_url((string) ($payload['userinfo_endpoint'] ?? ''), PHP_URL_PATH))->toBe($userinfoPath)
        ->and(parse_url((string) ($payload['end_session_endpoint'] ?? ''), PHP_URL_PATH))->toBe($endSessionPath)
        ->and(parse_url((string) ($payload['jwks_uri'] ?? ''), PHP_URL_PATH))->toBe($jwksPath);
});
