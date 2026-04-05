<?php

use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Models\UserClientConsent;
use App\Services\OAuth\OAuthRememberedConsentService;
use App\Services\OAuth\RememberedConsentInvalidationService;
use App\Support\OAuth\RememberedConsentRevocationReasons;
use Illuminate\Support\Facades\Artisan;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutVite();

    Scope::factory()->create(['name' => 'OpenID', 'code' => 'openid', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Profile', 'code' => 'profile', 'is_active' => true]);
});

function invalidationAdmin(array $abilities = []): User
{
    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        Permission::findOrCreate($ability, 'web');
    }

    if ($abilities !== []) {
        $user->givePermissionTo($abilities);
    }

    return $user;
}

function invalidationClient(array $overrides = []): SsoClient
{
    $policy = TokenPolicy::query()->create([
        'name' => 'Remembered Consent Invalidation Policy',
        'code' => 'remembered.invalidate.'.fake()->unique()->numerify('###'),
        'description' => 'Remembered consent invalidation test policy.',
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
        'client_id' => 'invalidate_client_'.fake()->unique()->numerify('###'),
        'token_policy_id' => $policy->id,
        'is_active' => true,
        'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED,
        'consent_bypass_allowed' => false,
        'is_first_party' => true,
    ], $overrides));

    $client->redirectUris()->create([
        'uri' => 'https://portal.example.com/callback',
        'uri_hash' => hash('sha256', 'https://portal.example.com/callback'),
        'is_primary' => true,
    ]);
    $client->scopes()->sync(Scope::query()->whereIn('code', ['openid', 'profile'])->pluck('id')->all());

    return $client->fresh(['redirectUris', 'scopes', 'tokenPolicy']);
}

function storedInvalidationConsent(
    OAuthRememberedConsentService $service,
    User $user,
    SsoClient $client,
    array $scopeCodes = ['openid', 'profile'],
): UserClientConsent {
    return $service->storeApprovedConsent(
        user: $user,
        client: $client,
        scopeCodes: $scopeCodes,
        redirectUri: 'https://portal.example.com/callback',
    );
}

it('invalidates active remembered consents when trust tier changes', function (): void {
    $rememberedConsentService = app(OAuthRememberedConsentService::class);
    $owner = User::factory()->create();
    $client = invalidationClient([
        'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED,
        'consent_bypass_allowed' => false,
    ]);
    $grant = storedInvalidationConsent($rememberedConsentService, $owner, $client);
    $admin = invalidationAdmin(['clients.update']);

    $this->actingAs($admin)
        ->put(route('admin.sso-clients.update', $client), [
            'name' => $client->name,
            'redirect_uris' => ['https://portal.example.com/callback'],
            'scopes' => ['openid', 'profile'],
            'is_active' => true,
            'token_policy_id' => $client->token_policy_id,
            'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
            'is_first_party' => true,
            'consent_bypass_allowed' => false,
        ])
        ->assertRedirect(route('admin.sso-clients.index'));

    expect($grant->fresh()->revoked_at)->not->toBeNull()
        ->and($grant->fresh()->revocation_reason)->toBe(RememberedConsentRevocationReasons::TRUST_TIER_CHANGED)
        ->and($rememberedConsentService->isConsentUsable($grant->fresh()))->toBeFalse();

    $bulkActivity = Activity::query()
        ->where('event', 'oauth.remembered_consent.bulk_invalidated_for_client')
        ->latest()
        ->first();

    expect($bulkActivity)->not->toBeNull()
        ->and($bulkActivity?->properties->get('affected_count'))->toBe(1)
        ->and($bulkActivity?->properties->get('reason'))->toBe(RememberedConsentRevocationReasons::TRUST_TIER_CHANGED);
});

it('invalidates active remembered consents when consent bypass allowed changes', function (): void {
    $rememberedConsentService = app(OAuthRememberedConsentService::class);
    $owner = User::factory()->create();
    $client = invalidationClient([
        'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_TRUSTED,
        'consent_bypass_allowed' => false,
    ]);
    $grant = storedInvalidationConsent($rememberedConsentService, $owner, $client);
    $admin = invalidationAdmin(['clients.update']);

    $this->actingAs($admin)
        ->put(route('admin.sso-clients.update', $client), [
            'name' => $client->name,
            'redirect_uris' => ['https://portal.example.com/callback'],
            'scopes' => ['openid', 'profile'],
            'is_active' => true,
            'token_policy_id' => $client->token_policy_id,
            'trust_tier' => $client->trust_tier,
            'is_first_party' => true,
            'consent_bypass_allowed' => true,
        ])
        ->assertRedirect(route('admin.sso-clients.index'));

    expect($grant->fresh()->revocation_reason)->toBe(RememberedConsentRevocationReasons::CONSENT_BYPASS_POLICY_CHANGED);
});

it('does not invalidate remembered consents when trust fields did not actually change', function (): void {
    $rememberedConsentService = app(OAuthRememberedConsentService::class);
    $owner = User::factory()->create();
    $client = invalidationClient([
        'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED,
        'consent_bypass_allowed' => false,
    ]);
    $grant = storedInvalidationConsent($rememberedConsentService, $owner, $client);
    $admin = invalidationAdmin(['clients.update']);

    $this->actingAs($admin)
        ->put(route('admin.sso-clients.update', $client), [
            'name' => 'Portal Client Renamed',
            'redirect_uris' => ['https://portal.example.com/callback'],
            'scopes' => ['openid', 'profile'],
            'is_active' => true,
            'token_policy_id' => $client->token_policy_id,
            'trust_tier' => $client->trust_tier,
            'is_first_party' => true,
            'consent_bypass_allowed' => false,
        ])
        ->assertRedirect(route('admin.sso-clients.index'));

    expect($grant->fresh()->revoked_at)->toBeNull()
        ->and($rememberedConsentService->isConsentUsable($grant->fresh()))->toBeTrue();

    expect(Activity::query()->where('event', 'oauth.remembered_consent.bulk_invalidated_for_client')->count())->toBe(0);
});

it('already revoked grants are handled safely during invalidation', function (): void {
    $rememberedConsentService = app(OAuthRememberedConsentService::class);
    $invalidationService = app(RememberedConsentInvalidationService::class);
    $owner = User::factory()->create();
    $client = invalidationClient();
    $grant = storedInvalidationConsent($rememberedConsentService, $owner, $client);
    $rememberedConsentService->revokeConsent($grant, RememberedConsentRevocationReasons::SECURITY_INCIDENT);

    $result = $invalidationService->invalidateConsentGrant(
        $grant->fresh(),
        RememberedConsentRevocationReasons::TRUST_TIER_CHANGED,
    );

    expect($result['invalidated'])->toBeFalse()
        ->and($grant->fresh()->revocation_reason)->toBe(RememberedConsentRevocationReasons::SECURITY_INCIDENT);
});

it('invalidates active remembered consents for a consent policy version change', function (): void {
    config()->set('services.oauth.consent_policy_version', 'remembered-consent-v1');

    $rememberedConsentService = app(OAuthRememberedConsentService::class);
    $invalidationService = app(RememberedConsentInvalidationService::class);
    $owner = User::factory()->create();
    $client = invalidationClient();
    $grant = storedInvalidationConsent($rememberedConsentService, $owner, $client);

    config()->set('services.oauth.consent_policy_version', 'remembered-consent-v2');

    $result = $invalidationService->invalidateForPolicyVersionChange('remembered-consent-v2', 'remembered-consent-v1');

    expect($result['affected_count'])->toBe(1)
        ->and($grant->fresh()->revocation_reason)->toBe(RememberedConsentRevocationReasons::CONSENT_POLICY_VERSION_CHANGED)
        ->and($rememberedConsentService->isConsentUsable($grant->fresh()))->toBeFalse();

    $bulkActivity = Activity::query()
        ->where('event', 'oauth.remembered_consent.bulk_invalidated_for_policy_version')
        ->latest()
        ->first();

    expect($bulkActivity)->not->toBeNull()
        ->and($bulkActivity?->properties->get('affected_count'))->toBe(1)
        ->and($bulkActivity?->properties->get('old_value'))->toBe('remembered-consent-v1')
        ->and($bulkActivity?->properties->get('new_value'))->toBe('remembered-consent-v2');
});

it('policy version invalidation is idempotent on repeated runs', function (): void {
    config()->set('services.oauth.consent_policy_version', 'remembered-consent-v1');

    $rememberedConsentService = app(OAuthRememberedConsentService::class);
    $invalidationService = app(RememberedConsentInvalidationService::class);
    $owner = User::factory()->create();
    $client = invalidationClient();
    storedInvalidationConsent($rememberedConsentService, $owner, $client);

    config()->set('services.oauth.consent_policy_version', 'remembered-consent-v2');

    $first = $invalidationService->invalidateForPolicyVersionChange('remembered-consent-v2', 'remembered-consent-v1');
    $second = $invalidationService->invalidateForPolicyVersionChange('remembered-consent-v2', 'remembered-consent-v1');

    expect($first['affected_count'])->toBe(1)
        ->and($second['affected_count'])->toBe(0);
});

it('invalidated grant is no longer reusable by remembered consent decision logic', function (): void {
    $rememberedConsentService = app(OAuthRememberedConsentService::class);
    $owner = User::factory()->create();
    $client = invalidationClient([
        'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED,
        'consent_bypass_allowed' => false,
    ]);
    $grant = storedInvalidationConsent($rememberedConsentService, $owner, $client);
    $grant->forceFill([
        'revoked_at' => now(),
        'revocation_reason' => RememberedConsentRevocationReasons::TRUST_TIER_CHANGED,
    ])->save();

    $decision = $rememberedConsentService->evaluateReusableConsent(
        user: $owner,
        client: $client,
        scopeCodes: ['openid', 'profile'],
        redirectUri: 'https://portal.example.com/callback',
    );

    expect($decision->shouldReuse)->toBeFalse()
        ->and($decision->reason)->toBe('remembered_consent_revoked');
});

it('artisan command invalidates remembered consents for the target policy version', function (): void {
    config()->set('services.oauth.consent_policy_version', 'remembered-consent-v1');

    $rememberedConsentService = app(OAuthRememberedConsentService::class);
    $owner = User::factory()->create();
    $client = invalidationClient();
    $grant = storedInvalidationConsent($rememberedConsentService, $owner, $client);

    config()->set('services.oauth.consent_policy_version', 'remembered-consent-v2');

    Artisan::call('oauth:invalidate-remembered-consents', [
        '--policy-version' => 'remembered-consent-v2',
        '--old-version' => 'remembered-consent-v1',
    ]);

    expect(Artisan::output())->toContain('Invalidated 1 remembered consent grant(s) for policy version [remembered-consent-v2].')
        ->and($grant->fresh()->revocation_reason)->toBe(RememberedConsentRevocationReasons::CONSENT_POLICY_VERSION_CHANGED);
});
