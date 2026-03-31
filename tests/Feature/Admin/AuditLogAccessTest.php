<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutVite();
});

function auditLogUser(array $abilities = []): User
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

it('allows audit log page access for users with audit log permission', function () {
    $user = auditLogUser(['audit-logs.viewAny']);

    $this->actingAs($user)
        ->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/PlaceholderPage')
            ->where('page.key', 'audit-logs')
            ->where('page.permission', 'audit-logs.viewAny'));
});

it('forbids audit log page access for authenticated users without permission', function () {
    $user = auditLogUser();

    $this->actingAs($user)
        ->get(route('admin.audit-logs.index'))
        ->assertForbidden();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'security',
        'event' => 'security.authorization.denied',
        'causer_id' => $user->id,
        'causer_type' => User::class,
    ]);
});

it('redirects guests away from the audit log page', function () {
    $this->get(route('admin.audit-logs.index'))
        ->assertRedirect(route('login'));
});
