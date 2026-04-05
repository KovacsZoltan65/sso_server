<?php

use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Models\UserClientConsent;
use App\Services\OAuth\OAuthRememberedConsentService;
use App\Support\Permissions\RememberedConsentPermissions;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutVite();

    Scope::factory()->create(['name' => 'OpenID', 'code' => 'openid', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Profile', 'code' => 'profile', 'is_active' => true]);
});

function rememberedConsentAdmin(array $abilities = []): User
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

function rememberedConsentAdminClient(array $overrides = []): SsoClient
{
    $policy = TokenPolicy::query()->create([
        'name' => 'Remembered Consent Admin Policy',
        'code' => 'remembered.admin.'.fake()->unique()->numerify('###'),
        'description' => 'Remembered consent admin test policy.',
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
        'client_id' => 'remembered_admin_client_'.fake()->unique()->numerify('###'),
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

function storedRememberedConsent(
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

it('authorized admin can view remembered consent index with the required fields', function (): void {
    $service = app(OAuthRememberedConsentService::class);
    $owner = User::factory()->create([
        'name' => 'Consent Owner',
        'email' => 'owner@example.com',
    ]);
    $client = rememberedConsentAdminClient([
        'name' => 'Portal Client',
        'client_id' => 'portal-client',
    ]);
    storedRememberedConsent($service, $owner, $client);

    $admin = rememberedConsentAdmin([RememberedConsentPermissions::VIEW_ANY]);

    $this->actingAs($admin)
        ->get(route('admin.remembered-consents.index', ['global' => 'Portal']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('RememberedConsents/Index')
            ->has('rows', 1)
            ->where('rows.0.userName', 'Consent Owner')
            ->where('rows.0.userEmail', 'owner@example.com')
            ->where('rows.0.clientName', 'Portal Client')
            ->where('rows.0.clientPublicId', 'portal-client')
            ->where('rows.0.scopeCodes.0', 'openid')
            ->where('rows.0.scopeCodes.1', 'profile')
            ->where('rows.0.status', 'active')
            ->where('rows.0.revocationReason', null)
            ->where('filters.global', 'Portal')
            ->where('canManageRememberedConsents', false)
            ->has('revocationReasonOptions', 4));
});

it('filters remembered consents by status client and user', function (): void {
    $service = app(OAuthRememberedConsentService::class);
    $targetUser = User::factory()->create([
        'name' => 'Target User',
        'email' => 'target@example.com',
    ]);
    $otherUser = User::factory()->create();
    $targetClient = rememberedConsentAdminClient([
        'name' => 'Target Client',
        'client_id' => 'target-client',
    ]);
    $otherClient = rememberedConsentAdminClient([
        'name' => 'Other Client',
        'client_id' => 'other-client',
    ]);

    $revoked = storedRememberedConsent($service, $targetUser, $targetClient);
    $service->revokeConsent($revoked, 'security_incident');

    storedRememberedConsent($service, $targetUser, $otherClient);
    $expired = storedRememberedConsent($service, $otherUser, $targetClient);
    $expired->forceFill(['expires_at' => now()->subMinute()])->save();

    $admin = rememberedConsentAdmin([RememberedConsentPermissions::VIEW_ANY]);

    $this->actingAs($admin)
        ->get(route('admin.remembered-consents.index', [
            'status' => 'revoked',
            'client_id' => $targetClient->id,
            'user_id' => $targetUser->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.id', $revoked->id)
            ->where('rows.0.status', 'revoked')
            ->where('filters.status', 'revoked')
            ->where('filters.client_id', (string) $targetClient->id)
            ->where('filters.user_id', (string) $targetUser->id));
});

it('forbids remembered consent index when unauthorized', function (): void {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.remembered-consents.index'))
        ->assertForbidden();
});

it('active remembered consent can be revoked and is no longer reusable', function (): void {
    $rememberedConsentService = app(OAuthRememberedConsentService::class);
    $owner = User::factory()->create();
    $client = rememberedConsentAdminClient();
    $consent = storedRememberedConsent($rememberedConsentService, $owner, $client);
    $admin = rememberedConsentAdmin([
        RememberedConsentPermissions::VIEW_ANY,
        RememberedConsentPermissions::REVOKE,
    ]);

    Carbon::setTestNow(now()->addMinute());

    $this->actingAs($admin)
        ->post(route('admin.remembered-consents.revoke', $consent), [
            'revocation_reason' => 'security_incident',
        ])
        ->assertOk()
        ->assertJsonPath('data.id', $consent->id)
        ->assertJsonPath('data.already_revoked', false)
        ->assertJsonPath('data.status', 'revoked');

    $consent->refresh();

    expect($consent->revoked_at)->not->toBeNull()
        ->and($consent->revocation_reason)->toBe('security_incident')
        ->and($consent->currentStatus())->toBe('revoked')
        ->and($rememberedConsentService->isConsentUsable($consent))->toBeFalse()
        ->and($rememberedConsentService->findActiveConsent(
            user: $owner,
            client: $client,
            scopeCodes: ['profile', 'openid'],
            redirectUri: 'https://portal.example.com/callback',
        ))->toBeNull();

    $activity = Activity::query()
        ->where('log_name', 'oauth')
        ->where('event', 'oauth.remembered_consent.revoked')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity?->properties->get('consent_id'))->toBe($consent->id)
        ->and($activity?->properties->get('client_id'))->toBe($client->id)
        ->and($activity?->properties->get('client_public_id'))->toBe($client->client_id)
        ->and($activity?->properties->get('target_user_id'))->toBe($owner->id)
        ->and($activity?->properties->get('actor_user_id'))->toBe($admin->id)
        ->and($activity?->properties->get('revocation_reason'))->toBe('security_incident')
        ->and($activity?->properties->get('previous_status'))->toBe('active')
        ->and($activity?->properties->get('new_status'))->toBe('revoked');

    Carbon::setTestNow();
});

it('already revoked remembered consent returns a controlled success response', function (): void {
    $rememberedConsentService = app(OAuthRememberedConsentService::class);
    $owner = User::factory()->create();
    $client = rememberedConsentAdminClient();
    $consent = storedRememberedConsent($rememberedConsentService, $owner, $client);
    $rememberedConsentService->revokeConsent($consent, 'security_incident');
    $originalRevokedAt = $consent->fresh()->revoked_at;

    $admin = rememberedConsentAdmin([RememberedConsentPermissions::REVOKE]);

    $this->actingAs($admin)
        ->post(route('admin.remembered-consents.revoke', $consent), [
            'revocation_reason' => 'admin_manual_revoke',
        ])
        ->assertOk()
        ->assertJsonPath('data.already_revoked', true)
        ->assertJsonPath('data.status', 'revoked');

    expect($consent->fresh()->revoked_at?->equalTo($originalRevokedAt))->toBeTrue()
        ->and($consent->fresh()->revocation_reason)->toBe('security_incident');
});

it('expired remembered consent can still be explicitly revoked by admin', function (): void {
    $rememberedConsentService = app(OAuthRememberedConsentService::class);
    $owner = User::factory()->create();
    $client = rememberedConsentAdminClient();
    $consent = storedRememberedConsent($rememberedConsentService, $owner, $client);
    $consent->forceFill(['expires_at' => now()->subMinute()])->save();

    $admin = rememberedConsentAdmin([RememberedConsentPermissions::REVOKE]);

    $this->actingAs($admin)
        ->post(route('admin.remembered-consents.revoke', $consent), [
            'revocation_reason' => 'client_access_removed',
        ])
        ->assertOk()
        ->assertJsonPath('data.already_revoked', false)
        ->assertJsonPath('data.status', 'revoked');

    expect($consent->fresh()->revoked_at)->not->toBeNull()
        ->and($consent->fresh()->revocation_reason)->toBe('client_access_removed')
        ->and($consent->fresh()->currentStatus())->toBe('revoked');
});

it('forbids remembered consent revoke when unauthorized', function (): void {
    $rememberedConsentService = app(OAuthRememberedConsentService::class);
    $owner = User::factory()->create();
    $client = rememberedConsentAdminClient();
    $consent = storedRememberedConsent($rememberedConsentService, $owner, $client);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.remembered-consents.revoke', $consent), [
            'revocation_reason' => 'admin_manual_revoke',
        ])
        ->assertForbidden();
});

it('returns not found for a missing remembered consent revoke target', function (): void {
    $admin = rememberedConsentAdmin([RememberedConsentPermissions::REVOKE]);

    $this->actingAs($admin)
        ->post(route('admin.remembered-consents.revoke', 999999), [
            'revocation_reason' => 'admin_manual_revoke',
        ])
        ->assertNotFound();
});
