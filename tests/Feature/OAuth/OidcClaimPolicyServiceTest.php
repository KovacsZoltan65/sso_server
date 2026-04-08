<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\OAuth\OidcClaimPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('provides the supported claims and userinfo scope mapping from a central policy', function (): void {
    $service = app(OidcClaimPolicyService::class);

    expect($service->supportedClaims())->toBe(['sub', 'name', 'email', 'email_verified', 'sid'])
        ->and($service->allowedClaimNamesForScopes(['openid'], 'userinfo'))->toBe(['sub'])
        ->and($service->allowedClaimNamesForScopes(['openid', 'profile'], 'userinfo'))->toBe(['sub', 'name'])
        ->and($service->allowedClaimNamesForScopes(['openid', 'profile', 'email'], 'userinfo'))->toBe(['sub', 'name', 'email', 'email_verified'])
        ->and($service->allowedClaimNamesForScopes(['openid', 'profile', 'email'], 'id_token'))->toBe([]);
});

it('builds userinfo claims through the central claim policy without leaking unsupported fields', function (): void {
    $service = app(OidcClaimPolicyService::class);
    $user = User::factory()->create([
        'name' => 'Teszt Elek',
        'email' => 'elek@example.com',
        'email_verified_at' => now(),
    ]);

    expect($service->userInfoClaimsForUser($user, ['openid'], (string) $user->id))->toBe([
        'sub' => (string) $user->id,
    ])->and($service->userInfoClaimsForUser($user, ['openid', 'profile', 'email'], (string) $user->id))->toBe([
        'sub' => (string) $user->id,
        'name' => 'Teszt Elek',
        'email' => 'elek@example.com',
        'email_verified' => true,
    ]);
});
