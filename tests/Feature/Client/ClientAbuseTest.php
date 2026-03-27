<?php

use App\Models\ClientSecret;
use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutVite();

    Scope::factory()->create([
        'name' => 'OpenID',
        'code' => 'openid',
        'is_active' => true,
    ]);
});

function clientAbuseUser(array $abilities = []): User
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

it('forbids client edit access when user only has viewAny permission', function () {
    $client = SsoClient::factory()->create();
    $user = clientAbuseUser(['clients.viewAny']);

    $this->actingAs($user)
        ->get(route('admin.sso-clients.edit', $client))
        ->assertForbidden();
});

it('forbids revoking a secret through a mismatched client route', function () {
    $client = SsoClient::factory()->create();
    $otherClient = SsoClient::factory()->create();
    $secret = $otherClient->secrets()->create([
        'name' => 'Other secret',
        'secret_hash' => Hash::make('other-secret'),
        'last_four' => '2222',
        'is_active' => true,
    ]);
    $user = clientAbuseUser(['clients.manageSecrets']);

    $this->actingAs($user)
        ->delete(route('admin.sso-clients.revoke-secret', [$client, $secret]))
        ->assertForbidden();

    expect($secret->fresh()->revoked_at)->toBeNull();
    expect($secret->fresh()->is_active)->toBeTrue();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'security',
        'event' => 'authorization.denied',
        'causer_id' => $user->id,
        'causer_type' => User::class,
    ]);
});

it('returns not found when client update targets a non-existing id', function () {
    $user = clientAbuseUser(['clients.update']);

    $this->actingAs($user)
        ->put(route('admin.sso-clients.update', 999999), [
            'name' => 'Missing Client',
            'redirect_uris' => ['https://portal.example.com/callback'],
            'scopes' => ['openid'],
            'is_active' => true,
            'token_policy_id' => null,
        ])
        ->assertNotFound();
});
