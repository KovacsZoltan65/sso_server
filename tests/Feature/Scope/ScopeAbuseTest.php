<?php

use App\Models\Scope;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutVite();
});

function scopeAbuseUser(array $abilities = []): User
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

it('forbids scope edit access when user only has viewAny permission', function () {
    $scope = Scope::factory()->create();
    $user = scopeAbuseUser(['scopes.viewAny']);

    $this->actingAs($user)
        ->get(route('admin.scopes.edit', $scope))
        ->assertForbidden();
});

it('forbids scope bulk delete when caller only has delete permission', function () {
    $scopes = Scope::factory()->count(2)->create();
    $user = scopeAbuseUser(['scopes.delete']);

    $this->actingAs($user)
        ->deleteJson(route('admin.scopes.bulk-destroy'), [
            'ids' => $scopes->pluck('id')->all(),
        ])
        ->assertForbidden();

    foreach ($scopes as $scope) {
        $this->assertDatabaseHas('scopes', [
            'id' => $scope->id,
        ]);
    }
});

it('returns not found when scope edit targets a non-existing id', function () {
    $user = scopeAbuseUser(['scopes.update']);

    $this->actingAs($user)
        ->get(route('admin.scopes.edit', 999999))
        ->assertNotFound();
});
