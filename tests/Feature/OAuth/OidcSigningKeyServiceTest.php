<?php

declare(strict_types=1);

use App\Services\OAuth\OidcJwksService;
use App\Services\OAuth\OidcKeyRotationService;
use App\Services\OAuth\OidcSigningKeyService;

beforeEach(function (): void {
    $registryPath = storage_path('framework/testing/oidc-signing-registry.json');

    if (is_file($registryPath)) {
        unlink($registryPath);
    }

    config()->set('oidc.signing.active_kid', 'active-key');
    config()->set('oidc.signing.registry_path', $registryPath);
    config()->set('oidc.signing.key_directory', storage_path('framework/testing/oidc-keys'));
    config()->set('oidc.signing.retiring_grace_period_seconds', 3600);
    config()->set('oidc.signing.keys', [
        [
            'kid' => 'active-key',
            'status' => 'active',
            'alg' => 'RS256',
            'private_key_path' => base_path('tests/Fixtures/oidc/private.pem'),
            'public_key_path' => base_path('tests/Fixtures/oidc/public.pem'),
            'published' => true,
        ],
        [
            'kid' => 'legacy-key',
            'status' => 'retiring',
            'alg' => 'RS256',
            'private_key_path' => base_path('tests/Fixtures/oidc/legacy-private.pem'),
            'public_key_path' => base_path('tests/Fixtures/oidc/legacy-public.pem'),
            'published' => true,
        ],
    ]);
});

it('selects exactly one active signing key from the configured registry', function (): void {
    $service = app(OidcSigningKeyService::class);

    expect($service->getActiveSigningKey())->toMatchArray([
        'kid' => 'active-key',
        'alg' => 'RS256',
        'published' => true,
        'status' => 'active',
    ]);

    expect($service->getPublishedVerificationKeys())->toHaveCount(2);
    expect($service->getRetiringKeys())->toHaveCount(1);
});

it('publishes lifecycle verification keys in the jwks', function (): void {
    $jwks = app(OidcJwksService::class)->currentJwkSet();

    expect($jwks['keys'])->toHaveCount(2)
        ->and(collect($jwks['keys'])->pluck('kid')->all())->toBe(['active-key', 'legacy-key']);
});

it('does not publish disabled keys in the jwks', function (): void {
    config()->set('oidc.signing.keys.1.status', 'disabled');

    $jwks = app(OidcJwksService::class)->currentJwkSet();

    expect($jwks['keys'])->toHaveCount(1)
        ->and(collect($jwks['keys'])->pluck('kid')->all())->toBe(['active-key']);

    expect(fn () => app(OidcSigningKeyService::class)->findKeyByKid('legacy-key'))
        ->toThrow(RuntimeException::class, 'OIDC signing key [legacy-key] is not configured.');
});

it('verifies tokens signed by a retiring key during the grace period', function (): void {
    $service = app(OidcSigningKeyService::class);
    $input = 'header.payload';
    $privateKey = openssl_pkey_get_private(file_get_contents(base_path('tests/Fixtures/oidc/legacy-private.pem')));

    expect($privateKey)->not->toBeFalse();

    $signature = '';
    openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    openssl_free_key($privateKey);

    expect($service->verify($input, $signature, 'legacy-key'))->toBeTrue();
});

it('does not allow signing with a non-active lifecycle key', function (): void {
    $service = app(OidcSigningKeyService::class);

    expect(fn () => $service->sign('header.payload', $service->getRetiringKeys()[0]))
        ->toThrow(RuntimeException::class, 'OIDC signing can only use the active lifecycle key.');
});

it('fails fast when more than one lifecycle key is active', function (): void {
    config()->set('oidc.signing.active_kid', '');
    config()->set('oidc.signing.keys.1.status', 'active');

    expect(fn () => app(OidcSigningKeyService::class)->validateKeyConfiguration())
        ->toThrow(RuntimeException::class, 'OIDC signing key configuration must define exactly one active key.');
});

it('fails fast when the multi-key configuration contains duplicate kids', function (): void {
    config()->set('oidc.signing.keys', [
        [
            'kid' => 'duplicate-key',
            'alg' => 'RS256',
            'private_key_path' => base_path('tests/Fixtures/oidc/private.pem'),
            'public_key_path' => base_path('tests/Fixtures/oidc/public.pem'),
            'published' => true,
        ],
        [
            'kid' => 'duplicate-key',
            'alg' => 'RS256',
            'private_key_path' => null,
            'public_key_path' => base_path('tests/Fixtures/oidc/legacy-public.pem'),
            'published' => true,
        ],
    ]);

    expect(fn () => app(OidcSigningKeyService::class)->getActiveSigningKey())
        ->toThrow(RuntimeException::class, 'OIDC signing kid [duplicate-key] is duplicated.');
});

it('fails fast when configured active_kid conflicts with the lifecycle active key', function (): void {
    config()->set('oidc.signing.active_kid', 'missing-active-key');

    expect(fn () => app(OidcSigningKeyService::class)->getActiveSigningKey())
        ->toThrow(RuntimeException::class, 'OIDC signing active_kid [missing-active-key] does not match lifecycle active key [active-key].');
});

it('rotates by creating a published key, activating it, and retiring the previous active key', function (): void {
    $rotationService = app(OidcKeyRotationService::class);

    $result = $rotationService->rotate('rotated-key');
    $keys = collect($rotationService->listKeys())->keyBy('kid');

    expect($result['previous_active_kid'])->toBe('active-key')
        ->and($result['new_key']['kid'])->toBe('rotated-key')
        ->and($keys['rotated-key']['status'])->toBe('active')
        ->and($keys['active-key']['status'])->toBe('retiring')
        ->and($keys['active-key']['retiring_since'])->toBeString()
        ->and(app(OidcSigningKeyService::class)->getActiveSigningKey()['kid'])->toBe('rotated-key');

    $jwks = app(OidcJwksService::class)->currentJwkSet();

    expect(collect($jwks['keys'])->pluck('kid')->all())
        ->toContain('active-key')
        ->toContain('rotated-key');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.keys.rotation_executed',
        'description' => 'OIDC signing key rotation workflow executed.',
    ]);
});

it('blocks disabling a retiring key before the grace period elapses', function (): void {
    $rotationService = app(OidcKeyRotationService::class);
    $rotationService->retireKey('legacy-key');

    $eligibility = app(OidcSigningKeyService::class)->getDisableEligibility('legacy-key');

    expect($eligibility['eligible'])->toBeFalse()
        ->and($eligibility['reason'])->toBe('grace_period_not_elapsed')
        ->and($eligibility['retiring_since'])->toBeString();

    expect(fn () => $rotationService->disableKey('legacy-key'))
        ->toThrow(RuntimeException::class, 'OIDC signing key [legacy-key] cannot be disabled: grace_period_not_elapsed.');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.keys.disable_blocked',
        'description' => 'OIDC signing key disable blocked by lifecycle guard.',
    ]);
});

it('allows disabling a retiring key after the grace period elapses and removes it from jwks', function (): void {
    $rotationService = app(OidcKeyRotationService::class);
    $rotationService->retireKey('legacy-key');

    $registryPath = config('oidc.signing.registry_path');
    $registry = json_decode(file_get_contents($registryPath), true);
    $registry['keys'][1]['retiring_since'] = now()->subHours(2)->toIso8601String();
    file_put_contents($registryPath, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    $eligibility = app(OidcSigningKeyService::class)->getDisableEligibility('legacy-key');

    expect($eligibility['eligible'])->toBeTrue()
        ->and($eligibility['reason'])->toBe('retiring_key_can_be_disabled');

    $rotationService->disableKey('legacy-key');

    $keys = collect($rotationService->listKeys())->keyBy('kid');
    $jwks = app(OidcJwksService::class)->currentJwkSet();

    expect($keys['legacy-key']['status'])->toBe('disabled')
        ->and($keys['legacy-key']['disabled_at'])->toBeString()
        ->and(collect($jwks['keys'])->pluck('kid')->all())->not->toContain('legacy-key');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.keys.disabled_after_grace',
        'description' => 'OIDC signing key disabled after grace period.',
    ]);
});

it('does not allow disabling the active key directly', function (): void {
    $rotationService = app(OidcKeyRotationService::class);
    $eligibility = app(OidcSigningKeyService::class)->getDisableEligibility('active-key');

    expect($eligibility['eligible'])->toBeFalse()
        ->and($eligibility['reason'])->toBe('active_key_cannot_be_disabled');

    expect(fn () => $rotationService->disableKey('active-key'))
        ->toThrow(RuntimeException::class, 'OIDC signing key [active-key] cannot be disabled: active_key_cannot_be_disabled.');
});
