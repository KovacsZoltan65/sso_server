<?php

declare(strict_types=1);

use App\Services\OAuth\OidcJwksService;
use App\Services\OAuth\OidcSigningKeyService;

beforeEach(function (): void {
    config()->set('oidc.signing.active_kid', 'active-key');
    config()->set('oidc.signing.keys', [
        [
            'kid' => 'active-key',
            'alg' => 'RS256',
            'private_key_path' => base_path('tests/Fixtures/oidc/private.pem'),
            'public_key_path' => base_path('tests/Fixtures/oidc/public.pem'),
            'published' => true,
        ],
        [
            'kid' => 'legacy-key',
            'alg' => 'RS256',
            'private_key_path' => null,
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
    ]);

    expect($service->getPublishedVerificationKeys())->toHaveCount(2);
});

it('publishes every configured verification key in the jwks', function (): void {
    $jwks = app(OidcJwksService::class)->currentJwkSet();

    expect($jwks['keys'])->toHaveCount(2)
        ->and(collect($jwks['keys'])->pluck('kid')->all())->toBe(['active-key', 'legacy-key']);
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

it('fails fast when the configured active key is missing', function (): void {
    config()->set('oidc.signing.active_kid', 'missing-active-key');

    expect(fn () => app(OidcSigningKeyService::class)->getActiveSigningKey())
        ->toThrow(RuntimeException::class, 'OIDC signing key [missing-active-key] is not configured.');
});
