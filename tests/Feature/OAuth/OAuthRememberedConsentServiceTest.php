<?php

use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Models\UserClientConsent;
use App\Services\OAuth\OAuthRememberedConsentService;

beforeEach(function (): void {
    Scope::factory()->create(['name' => 'OpenID', 'code' => 'openid', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Profile', 'code' => 'profile', 'is_active' => true]);
});

function rememberedConsentClient(array $overrides = []): SsoClient
{
    $policy = TokenPolicy::query()->create([
        'name' => 'Remembered Consent Policy',
        'code' => 'remembered.consent.'.fake()->unique()->numerify('###'),
        'description' => 'Remembered consent test policy.',
        'access_token_ttl_minutes' => 60,
        'refresh_token_ttl_minutes' => 1440,
        'refresh_token_rotation_enabled' => true,
        'pkce_required' => true,
        'reuse_refresh_token_forbidden' => true,
        'is_default' => false,
        'is_active' => true,
    ]);

    $client = SsoClient::factory()->create(array_merge([
        'name' => 'Portal Client',
        'client_id' => 'remembered_client_'.fake()->unique()->numerify('###'),
        'token_policy_id' => $policy->id,
        'is_active' => true,
        'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED,
        'consent_bypass_allowed' => false,
    ], $overrides));

    $client->redirectUris()->create([
        'uri' => 'https://portal.example.com/callback',
        'uri_hash' => hash('sha256', 'https://portal.example.com/callback'),
        'is_primary' => true,
    ]);
    $client->scopes()->sync(Scope::query()->whereIn('code', ['openid', 'profile'])->pluck('id')->all());

    return $client->fresh(['redirectUris', 'scopes', 'tokenPolicy']);
}

it('creates an active remembered consent grant with scope snapshot and fingerprint', function (): void {
    $service = app(OAuthRememberedConsentService::class);
    $user = User::factory()->create();
    $client = rememberedConsentClient([
        'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_TRUSTED,
        'consent_bypass_allowed' => true,
    ]);

    $grant = $service->storeApprovedConsent(
        user: $user,
        client: $client,
        scopeCodes: ['profile', 'openid', 'openid'],
        redirectUri: 'https://portal.example.com/callback',
    );

    expect($grant->user_id)->toBe($user->id)
        ->and($grant->client_id)->toBe($client->id)
        ->and($grant->granted_scope_codes)->toBe(['openid', 'profile'])
        ->and($grant->granted_scope_fingerprint)->toBe($service->scopeFingerprint(['openid', 'profile']))
        ->and($grant->redirect_uri_hash)->toBe($service->redirectUriFingerprint('https://portal.example.com/callback'))
        ->and($grant->trust_tier_snapshot)->toBe(SsoClient::TRUST_TIER_FIRST_PARTY_TRUSTED)
        ->and($grant->consent_bypass_allowed_snapshot)->toBeTrue()
        ->and($grant->consent_policy_version)->toBe(OAuthRememberedConsentService::CONSENT_POLICY_VERSION)
        ->and($grant->revoked_at)->toBeNull();
});

it('builds a stable scope fingerprint regardless of scope order', function (): void {
    $service = app(OAuthRememberedConsentService::class);

    $first = $service->scopeFingerprint(['openid', 'profile']);
    $second = $service->scopeFingerprint(['profile', 'openid', 'openid']);

    expect($first)->toBe($second);
});

it('finds an active remembered consent grant by user client and fingerprint', function (): void {
    $service = app(OAuthRememberedConsentService::class);
    $user = User::factory()->create();
    $client = rememberedConsentClient();

    $stored = $service->storeApprovedConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    $found = $service->findActiveConsent(
        user: $user,
        client: $client,
        scopeCodes: ['profile', 'openid'],
        redirectUri: 'https://portal.example.com/callback',
    );

    expect($found)->not->toBeNull()
        ->and($found?->is($stored))->toBeTrue()
        ->and($service->isConsentUsable($found))->toBeTrue();
});

it('does not treat revoked remembered consent as active', function (): void {
    $service = app(OAuthRememberedConsentService::class);
    $user = User::factory()->create();
    $client = rememberedConsentClient();

    $grant = $service->storeApprovedConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    $service->revokeConsent($grant, 'user_revoked');

    $found = $service->findActiveConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    expect($found)->toBeNull();
    expect($grant->fresh()?->revocation_reason)->toBe('user_revoked');
});

it('does not treat expired remembered consent as active', function (): void {
    $service = app(OAuthRememberedConsentService::class);
    $user = User::factory()->create();
    $client = rememberedConsentClient();

    $grant = $service->storeApprovedConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    $grant->forceFill([
        'expires_at' => now()->subMinute(),
    ])->save();

    $found = $service->findActiveConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    expect($found)->toBeNull();
    expect($service->isConsentUsable($grant->fresh()))->toBeFalse();
});

it('marks remembered consent reusable only on exact user client scope redirect and snapshot match', function (): void {
    $service = app(OAuthRememberedConsentService::class);
    $user = User::factory()->create();
    $client = rememberedConsentClient([
        'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED,
        'consent_bypass_allowed' => false,
    ]);

    $grant = $service->storeApprovedConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    $decision = $service->evaluateReusableConsent(
        user: $user,
        client: $client,
        scopeCodes: ['profile', 'openid'],
        redirectUri: 'https://portal.example.com/callback',
    );

    expect($decision->shouldReuse)->toBeTrue()
        ->and($decision->reason)->toBe('remembered_consent_match')
        ->and($decision->consent?->is($grant))->toBeTrue();
});

it('does not reuse remembered consent on scope mismatch', function (): void {
    $service = app(OAuthRememberedConsentService::class);
    $user = User::factory()->create();
    $client = rememberedConsentClient();

    $service->storeApprovedConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid'],
        redirectUri: 'https://portal.example.com/callback',
    );

    $decision = $service->evaluateReusableConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    expect($decision->shouldReuse)->toBeFalse()
        ->and($decision->reason)->toBe('remembered_consent_scope_mismatch');
});

it('does not reuse remembered consent on redirect mismatch', function (): void {
    $service = app(OAuthRememberedConsentService::class);
    $user = User::factory()->create();
    $client = rememberedConsentClient();

    $service->storeApprovedConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    $decision = $service->evaluateReusableConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/other-callback',
    );

    expect($decision->shouldReuse)->toBeFalse()
        ->and($decision->reason)->toBe('remembered_consent_redirect_mismatch');
});

it('does not reuse remembered consent on trust tier mismatch', function (): void {
    $service = app(OAuthRememberedConsentService::class);
    $user = User::factory()->create();
    $client = rememberedConsentClient([
        'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED,
        'consent_bypass_allowed' => false,
    ]);

    $service->storeApprovedConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    $client->forceFill([
        'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
    ])->save();

    $decision = $service->evaluateReusableConsent(
        user: $user,
        client: $client->fresh(),
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    expect($decision->shouldReuse)->toBeFalse()
        ->and($decision->reason)->toBe('remembered_consent_trust_mismatch');
});

it('does not reuse remembered consent on bypass snapshot mismatch', function (): void {
    $service = app(OAuthRememberedConsentService::class);
    $user = User::factory()->create();
    $client = rememberedConsentClient([
        'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_TRUSTED,
        'consent_bypass_allowed' => false,
    ]);

    $service->storeApprovedConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    $client->forceFill([
        'consent_bypass_allowed' => true,
    ])->save();

    $decision = $service->evaluateReusableConsent(
        user: $user,
        client: $client->fresh(),
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    expect($decision->shouldReuse)->toBeFalse()
        ->and($decision->reason)->toBe('remembered_consent_bypass_mismatch');
});

it('does not reuse remembered consent on policy version mismatch', function (): void {
    $service = app(OAuthRememberedConsentService::class);
    $user = User::factory()->create();
    $client = rememberedConsentClient();

    $grant = $service->storeApprovedConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    $grant->forceFill([
        'consent_policy_version' => 'remembered-consent-v0',
    ])->save();

    $decision = $service->evaluateReusableConsent(
        user: $user,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    expect($decision->shouldReuse)->toBeFalse()
        ->and($decision->reason)->toBe('remembered_consent_policy_mismatch');
});

it('does not reuse remembered consent for a different user or client', function (): void {
    $service = app(OAuthRememberedConsentService::class);
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $client = rememberedConsentClient();
    $otherClient = rememberedConsentClient();

    $service->storeApprovedConsent(
        user: $owner,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    $userDecision = $service->evaluateReusableConsent(
        user: $otherUser,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    $clientDecision = $service->evaluateReusableConsent(
        user: $owner,
        client: $otherClient,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    expect($userDecision->shouldReuse)->toBeFalse()
        ->and($userDecision->reason)->toBe('remembered_consent_missing')
        ->and($clientDecision->shouldReuse)->toBeFalse()
        ->and($clientDecision->reason)->toBe('remembered_consent_missing');
});
