<?php

declare(strict_types=1);

use App\Models\SsoClient;
use App\Models\Token;
use App\Models\TokenPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutVite();
});

function tokenManager(array $abilities = []): User
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

function tokenFixture(array $overrides = []): Token
{
    $policy = TokenPolicy::factory()->create(['is_active' => true]);
    $client = SsoClient::factory()->create([
        'is_active' => true,
        'token_policy_id' => $policy->id,
    ]);
    $user = User::factory()->create();

    return Token::query()->create(array_merge([
        'sso_client_id' => $client->id,
        'user_id' => $user->id,
        'token_policy_id' => $policy->id,
        'family_id' => fake()->uuid(),
        'access_token_hash' => hash('sha256', fake()->sha256()),
        'refresh_token_hash' => hash('sha256', fake()->sha256()),
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
        'scopes' => ['openid'],
    ], $overrides));
}

it('authorized user can view the token index with token metadata', function (): void {
    $token = tokenFixture();
    $manager = tokenManager(['tokens.viewAny']);

    $this->actingAs($manager)
        ->get(route('admin.tokens.index', [
            'token_type' => 'refresh_token',
            'global' => $token->family_id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tokens/Index')
            ->has('rows', 1)
            ->where('rows.0.id', $token->id)
            ->where('rows.0.tokenType', 'refresh_token')
            ->where('rows.0.familyId', $token->family_id)
            ->where('filters.global', $token->family_id)
            ->where('filters.token_type', 'refresh_token')
            ->has('clientOptions')
            ->has('userOptions'));
});

it('forbids the token index when unauthorized', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.tokens.index'))
        ->assertForbidden();
});

it('filters tokens by state client user and type', function (): void {
    $manager = tokenManager(['tokens.viewAny']);

    $activeUser = User::factory()->create(['email' => 'active-token@example.com']);
    $otherUser = User::factory()->create(['email' => 'other-token@example.com']);
    $policy = TokenPolicy::factory()->create(['is_active' => true]);
    $clientA = SsoClient::factory()->create(['name' => 'Portal A', 'token_policy_id' => $policy->id, 'is_active' => true]);
    $clientB = SsoClient::factory()->create(['name' => 'Portal B', 'token_policy_id' => $policy->id, 'is_active' => true]);

    $activeToken = Token::query()->create([
        'sso_client_id' => $clientA->id,
        'user_id' => $activeUser->id,
        'token_policy_id' => $policy->id,
        'family_id' => fake()->uuid(),
        'access_token_hash' => hash('sha256', 'active-access'),
        'refresh_token_hash' => hash('sha256', 'active-refresh'),
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
        'scopes' => ['openid'],
    ]);

    $revokedToken = Token::query()->create([
        'sso_client_id' => $clientB->id,
        'user_id' => $otherUser->id,
        'token_policy_id' => $policy->id,
        'family_id' => fake()->uuid(),
        'access_token_hash' => hash('sha256', 'revoked-access'),
        'refresh_token_hash' => hash('sha256', 'revoked-refresh'),
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDay(),
        'refresh_token_revoked_at' => now(),
        'refresh_token_revoked_reason' => 'admin_revoked',
        'scopes' => ['openid'],
    ]);

    $replacement = tokenFixture([
        'sso_client_id' => $clientA->id,
        'user_id' => $activeUser->id,
        'token_policy_id' => $policy->id,
    ]);

    $rotatedToken = tokenFixture([
        'sso_client_id' => $clientA->id,
        'user_id' => $activeUser->id,
        'token_policy_id' => $policy->id,
        'refresh_token_revoked_at' => now(),
        'refresh_token_revoked_reason' => 'rotated',
        'refresh_token_used_at' => now(),
        'replaced_by_token_id' => $replacement->id,
    ]);

    $this->actingAs($manager)
        ->get(route('admin.tokens.index', [
            'token_type' => 'refresh_token',
            'state' => 'active',
            'client_id' => $clientA->id,
            'user_id' => $activeUser->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 2)
            ->where('rows', fn ($rows): bool => collect($rows)
                ->pluck('id')
                ->sort()
                ->values()
                ->all() === collect([$activeToken->id, $replacement->id])
                    ->sort()
                    ->values()
                    ->all())
            ->where('filters.state', 'active')
            ->where('filters.client_id', (string) $clientA->id)
            ->where('filters.user_id', (string) $activeUser->id));

    $this->actingAs($manager)
        ->get(route('admin.tokens.index', [
            'token_type' => 'refresh_token',
            'state' => 'revoked',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.id', $revokedToken->id)
            ->where('rows.0.status', 'revoked'));

    $this->actingAs($manager)
        ->get(route('admin.tokens.index', [
            'token_type' => 'refresh_token',
            'state' => 'rotated',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.id', $rotatedToken->id)
            ->where('rows.0.status', 'rotated'));

    $this->actingAs($manager)
        ->get(route('admin.tokens.index', [
            'token_type' => 'access_token',
            'client_id' => $clientB->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.token_type', 'access_token')
            ->where('rows.0.clientId', $clientB->id));
});

it('authorized admin can revoke an access token', function (): void {
    $manager = tokenManager(['tokens.revokeToken']);
    $token = tokenFixture();

    $this->actingAs($manager)
        ->postJson(route('admin.tokens.revoke', $token), [
            'token_type' => 'access_token',
            'reason' => 'admin_revoked',
        ])
        ->assertOk()
        ->assertJson([
            'message' => 'Token revoked successfully.',
            'data' => [
                'id' => $token->id,
            ],
        ]);

    $token->refresh();

    expect($token->access_token_revoked_at)->not->toBeNull()
        ->and($token->access_token_revoked_reason)->toBe('admin_revoked');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'oauth',
        'event' => 'oauth.token.revoked',
        'causer_id' => $manager->id,
    ]);
});

it('authorized admin can revoke a refresh token', function (): void {
    $manager = tokenManager(['tokens.revokeToken']);
    $token = tokenFixture();

    $this->actingAs($manager)
        ->postJson(route('admin.tokens.revoke', $token), [
            'token_type' => 'refresh_token',
            'reason' => 'admin_revoked',
        ])
        ->assertOk();

    $token->refresh();

    expect($token->refresh_token_revoked_at)->not->toBeNull()
        ->and($token->refresh_token_revoked_reason)->toBe('admin_revoked');
});

it('forbids revoking a token when unauthorized', function (): void {
    $user = User::factory()->create();
    $token = tokenFixture();

    $this->actingAs($user)
        ->postJson(route('admin.tokens.revoke', $token), [
            'token_type' => 'access_token',
        ])
        ->assertForbidden();
});
