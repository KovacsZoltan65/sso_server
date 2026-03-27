<?php

use App\Models\TokenPolicy;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutVite();
});

function tokenPolicyAbuseUser(array $abilities = []): User
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

it('forbids token policy edit access when user only has viewAny permission', function () {
    $tokenPolicy = TokenPolicy::factory()->create();
    $user = tokenPolicyAbuseUser(['token-policies.viewAny']);

    $this->actingAs($user)
        ->get(route('admin.token-policies.edit', $tokenPolicy))
        ->assertForbidden();
});

it('forbids token policy bulk delete when caller only has delete permission', function () {
    $tokenPolicies = TokenPolicy::factory()->count(2)->create(['is_default' => false]);
    $user = tokenPolicyAbuseUser(['token-policies.delete']);

    $this->actingAs($user)
        ->deleteJson(route('admin.token-policies.bulk-destroy'), [
            'ids' => $tokenPolicies->pluck('id')->all(),
        ])
        ->assertForbidden();

    foreach ($tokenPolicies as $tokenPolicy) {
        $this->assertDatabaseHas('token_policies', [
            'id' => $tokenPolicy->id,
        ]);
    }
});

it('returns not found when token policy edit targets a non-existing id', function () {
    $user = tokenPolicyAbuseUser(['token-policies.update']);

    $this->actingAs($user)
        ->get(route('admin.token-policies.edit', 999999))
        ->assertNotFound();
});
