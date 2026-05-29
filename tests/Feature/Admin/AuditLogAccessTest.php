<?php

use App\Models\User;
use App\Models\SsoClient;
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
    activity('oauth')
        ->event('oauth.token.issued')
        ->causedBy($user)
        ->withProperties([
            'severity' => 'info',
            'ip_address' => '127.0.0.1',
        ])
        ->log('OAuth token issued.');

    $this->actingAs($user)
        ->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/AuditLogs/Index')
            ->has('rows', 1)
            ->where('rows.0.event', 'oauth.token.issued')
            ->where('rows.0.severity', 'info')
            ->where('filters.search', null));
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

it('searches audit logs by event text', function (): void {
    $user = auditLogUser(['audit-logs.viewAny']);

    activity('oauth')->event('oauth.token.issued')->log('OAuth token issued.');
    activity('auth')->event('auth.login.success')->log('User login success.');

    $this->actingAs($user)
        ->get(route('admin.audit-logs.index', ['search' => 'oauth.token']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/AuditLogs/Index')
            ->has('rows', 1)
            ->where('rows.0.event', 'oauth.token.issued'));
});

it('filters audit logs by severity', function (): void {
    $user = auditLogUser(['audit-logs.viewAny']);

    activity('oauth')
        ->event('oauth.token.issued')
        ->withProperties(['severity' => 'info'])
        ->log('OAuth token issued.');
    activity('security')
        ->event('security.authorization.denied')
        ->withProperties(['severity' => 'error'])
        ->log('Authorization denied.');

    $this->actingAs($user)
        ->get(route('admin.audit-logs.index', ['severity' => 'error']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/AuditLogs/Index')
            ->has('rows', 1)
            ->where('rows.0.event', 'security.authorization.denied')
            ->where('rows.0.severity', 'error'));
});

it('validates date interval filters', function (): void {
    $user = auditLogUser(['audit-logs.viewAny']);

    $this->actingAs($user)
        ->from(route('admin.audit-logs.index'))
        ->get(route('admin.audit-logs.index', [
            'date_from' => '2026-05-02',
            'date_to' => '2026-05-01',
        ]))
        ->assertRedirect(route('admin.audit-logs.index'))
        ->assertSessionHasErrors(['date_to']);
});

it('rejects arbitrary sort fields', function (): void {
    $user = auditLogUser(['audit-logs.viewAny']);

    $this->actingAs($user)
        ->from(route('admin.audit-logs.index'))
        ->get(route('admin.audit-logs.index', ['sort_field' => 'properties->client_secret']))
        ->assertRedirect(route('admin.audit-logs.index'))
        ->assertSessionHasErrors(['sort_field']);
});

it('does not expose create update or delete audit log routes', function (): void {
    $user = auditLogUser(['audit-logs.viewAny', 'audit-logs.view']);
    $client = SsoClient::factory()->create();

    $activity = activity('admin.client')
        ->event('admin.client.created')
        ->performedOn($client)
        ->withProperties([
            'client_id' => $client->id,
            'client_secret' => 'should-not-render',
        ])
        ->log('Client created.');

    $this->actingAs($user)->post(route('admin.audit-logs.index'))->assertMethodNotAllowed();
    $this->actingAs($user)->put('/admin/audit-logs/'.$activity->id)->assertNotFound();
    $this->actingAs($user)->delete('/admin/audit-logs/'.$activity->id)->assertNotFound();
});
