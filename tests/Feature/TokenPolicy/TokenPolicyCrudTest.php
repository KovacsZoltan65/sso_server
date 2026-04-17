<?php

use App\Models\SsoClient;
use App\Models\TokenPolicy;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutVite();
});

function tokenPolicyManager(array $abilities = []): User
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

it('authorized user can view token policy index', function () {
    TokenPolicy::factory()->create([
        'name' => 'Default Web Policy',
        'code' => 'default.web',
        'access_token_ttl_minutes' => 60,
        'refresh_token_ttl_minutes' => 43200,
        'is_active' => true,
    ]);

    $user = tokenPolicyManager(['token-policies.viewAny']);

    $this->actingAs($user)
        ->get(route('admin.token-policies.index', ['global' => 'default']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('TokenPolicies/Index')
            ->has('rows', 1)
            ->where('rows.0.code', 'default.web')
            ->where('rows.0.accessTokenTtlMinutes', 60)
            ->where('filters.global', 'default')
            ->where('canManageTokenPolicies', false));
});

it('unauthorized user is forbidden from token policy index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.token-policies.index'))
        ->assertForbidden();
});

it('authorized user can view token policy create page', function () {
    $user = tokenPolicyManager(['token-policies.create']);

    $this->actingAs($user)
        ->get(route('admin.token-policies.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('TokenPolicies/Create'));
});

it('authorized user can store token policy', function () {
    $user = tokenPolicyManager(['token-policies.create']);

    $this->actingAs($user)
        ->post(route('admin.token-policies.store'), [
            'name' => 'Public Strict',
            'code' => 'public.strict',
            'description' => 'Strict settings for public clients.',
            'access_token_ttl_minutes' => 30,
            'refresh_token_ttl_minutes' => 1440,
            'refresh_token_rotation_enabled' => true,
            'pkce_required' => true,
            'reuse_refresh_token_forbidden' => true,
            'is_default' => false,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.token-policies.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('token_policies', [
        'code' => 'public.strict',
        'pkce_required' => true,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'admin.token_policy',
        'event' => 'admin.token_policy.created',
        'causer_id' => $user->id,
    ]);
});

it('store validation fails for invalid token policy payload', function () {
    TokenPolicy::factory()->create(['code' => 'default.web']);
    $user = tokenPolicyManager(['token-policies.create']);

    $this->actingAs($user)
        ->from(route('admin.token-policies.create'))
        ->post(route('admin.token-policies.store'), [
            'name' => '',
            'code' => 'INVALID CODE',
            'description' => str_repeat('a', 2001),
            'access_token_ttl_minutes' => 0,
            'refresh_token_ttl_minutes' => 0,
            'refresh_token_rotation_enabled' => false,
            'pkce_required' => false,
            'reuse_refresh_token_forbidden' => true,
            'is_default' => true,
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.token-policies.create'))
        ->assertSessionHasErrors([
            'name',
            'code',
            'description',
            'access_token_ttl_minutes',
            'refresh_token_ttl_minutes',
            'reuse_refresh_token_forbidden',
            'is_active',
        ]);
});

it('creates only one default token policy at a time', function () {
    TokenPolicy::factory()->create([
        'code' => 'default.web',
        'is_default' => true,
    ]);
    $user = tokenPolicyManager(['token-policies.create']);

    $this->actingAs($user)
        ->post(route('admin.token-policies.store'), [
            'name' => 'Mobile Default',
            'code' => 'mobile.default',
            'description' => 'Mobile-first defaults.',
            'access_token_ttl_minutes' => 45,
            'refresh_token_ttl_minutes' => 10080,
            'refresh_token_rotation_enabled' => true,
            'pkce_required' => true,
            'reuse_refresh_token_forbidden' => true,
            'is_default' => true,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.token-policies.index'));

    expect(TokenPolicy::where('is_default', true)->count())->toBe(1);
    expect(TokenPolicy::where('code', 'mobile.default')->value('is_default'))->toBeTrue();
});

it('authorized user can view token policy edit page', function () {
    $tokenPolicy = TokenPolicy::factory()->create([
        'name' => 'Public Strict',
        'code' => 'public.strict',
    ]);
    $user = tokenPolicyManager(['token-policies.update']);

    $this->actingAs($user)
        ->get(route('admin.token-policies.edit', $tokenPolicy))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('TokenPolicies/Edit')
            ->where('tokenPolicy.id', $tokenPolicy->id)
            ->where('tokenPolicy.code', 'public.strict'));
});

it('authorized user can update token policy', function () {
    $tokenPolicy = TokenPolicy::factory()->create([
        'code' => 'default.web',
        'is_default' => false,
        'is_active' => true,
    ]);
    $user = tokenPolicyManager(['token-policies.update']);

    $this->actingAs($user)
        ->put(route('admin.token-policies.update', $tokenPolicy), [
            'name' => 'Default Web Policy v2',
            'code' => 'default.web.v2',
            'description' => 'Updated defaults.',
            'access_token_ttl_minutes' => 90,
            'refresh_token_ttl_minutes' => 43200,
            'refresh_token_rotation_enabled' => true,
            'pkce_required' => false,
            'reuse_refresh_token_forbidden' => true,
            'is_default' => false,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.token-policies.index'))
        ->assertSessionHas('success');

    $tokenPolicy->refresh();

    expect($tokenPolicy->code)->toBe('default.web.v2');
    expect($tokenPolicy->access_token_ttl_minutes)->toBe(90);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'admin.token_policy',
        'event' => 'admin.token_policy.updated',
        'causer_id' => $user->id,
    ]);
});

it('prevents deleting the default token policy', function () {
    $tokenPolicy = TokenPolicy::factory()->create(['is_default' => true]);
    $user = tokenPolicyManager(['token-policies.delete']);

    $this->actingAs($user)
        ->deleteJson(route('admin.token-policies.destroy', $tokenPolicy))
        ->assertStatus(422)
        ->assertJsonPath('message', 'The default token policy cannot be deleted. Assign another default policy first.');
});

it('prevents deleting token policy assigned to clients', function () {
    $tokenPolicy = TokenPolicy::factory()->create(['is_default' => false]);
    SsoClient::factory()->create(['token_policy_id' => $tokenPolicy->id]);
    $user = tokenPolicyManager(['token-policies.delete']);

    $this->actingAs($user)
        ->deleteJson(route('admin.token-policies.destroy', $tokenPolicy))
        ->assertStatus(422)
        ->assertJsonPath('message', 'This token policy is assigned to clients and cannot be deleted.');
});

it('authorized user can bulk delete unused token policies', function () {
    $tokenPolicies = TokenPolicy::factory()->count(2)->create(['is_default' => false]);
    $user = tokenPolicyManager(['token-policies.deleteAny']);

    $this->actingAs($user)
        ->deleteJson(route('admin.token-policies.bulk-destroy'), [
            'ids' => $tokenPolicies->pluck('id')->all(),
        ])
        ->assertOk()
        ->assertJsonPath('meta.deletedCount', 2);

    $this->assertDatabaseCount('token_policies', 0);
});

it('prevents bulk delete when default token policy is selected', function () {
    $defaultPolicy = TokenPolicy::factory()->create(['is_default' => true]);
    $customPolicy = TokenPolicy::factory()->create(['is_default' => false]);
    $user = tokenPolicyManager(['token-policies.deleteAny']);

    $this->actingAs($user)
        ->deleteJson(route('admin.token-policies.bulk-destroy'), [
            'ids' => [$defaultPolicy->id, $customPolicy->id],
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The default token policy cannot be deleted. Assign another default policy first.');
});
