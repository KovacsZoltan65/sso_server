<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\SsoClient;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function auditApiUser(array $abilities = []): User
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

/**
 * @param array<string, mixed> $overrides
 * @param array<string, mixed> $properties
 */
function createAuditLog(array $overrides = [], array $properties = []): AuditLog
{
    /** @var AuditLog $auditLog */
    $auditLog = AuditLog::query()->create(array_merge([
        'log_name' => 'oauth',
        'description' => 'Audit entry for tests.',
        'subject_type' => null,
        'subject_id' => null,
        'causer_type' => null,
        'causer_id' => null,
        'event' => 'oauth.token.issued',
        'properties' => array_merge([
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
            'result' => 'success',
        ], $properties),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $auditLog->refresh();
}

it('forbids the audit log api list for users without permission', function (): void {
    $user = auditApiUser();

    $this->actingAs($user)
        ->getJson(route('api.admin.audit-logs.index'))
        ->assertForbidden()
        ->assertExactJson([
            'message' => 'Forbidden.',
            'data' => [],
            'meta' => [],
            'errors' => [],
        ]);
});

it('returns a paginated audit log list for authorized admins', function (): void {
    $user = auditApiUser(['audit-logs.viewAny', 'audit-logs.view']);
    createAuditLog();

    $this->actingAs($user)
        ->getJson(route('api.admin.audit-logs.index'))
        ->assertOk()
        ->assertJsonPath('message', 'Audit logs retrieved successfully.')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.eventType', 'oauth.token.issued');
});

it('filters audit logs by category', function (): void {
    $user = auditApiUser(['audit-logs.viewAny']);
    createAuditLog(['log_name' => 'oauth', 'event' => 'oauth.token.issued']);
    createAuditLog(['log_name' => 'security', 'event' => 'security.authorization.denied'], ['result' => 'failure']);

    $this->actingAs($user)
        ->getJson(route('api.admin.audit-logs.index', ['category' => 'security']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.category', 'security');
});

it('filters audit logs by severity', function (): void {
    $user = auditApiUser(['audit-logs.viewAny']);
    createAuditLog(['log_name' => 'oauth', 'event' => 'oauth.token.issued'], ['result' => 'success']);
    createAuditLog(['log_name' => 'security', 'event' => 'security.authorization.denied'], ['result' => 'failure']);

    $this->actingAs($user)
        ->getJson(route('api.admin.audit-logs.index', ['severity' => 'critical']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.severity', 'critical');
});

it('filters audit logs by date range', function (): void {
    $user = auditApiUser(['audit-logs.viewAny']);
    createAuditLog(['created_at' => Carbon::parse('2026-03-01 10:00:00'), 'updated_at' => Carbon::parse('2026-03-01 10:00:00')]);
    createAuditLog(['created_at' => Carbon::parse('2026-04-01 10:00:00'), 'updated_at' => Carbon::parse('2026-04-01 10:00:00')]);

    $this->actingAs($user)
        ->getJson(route('api.admin.audit-logs.index', [
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-01',
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.occurredAt', '2026-04-01 10:00:00');
});

it('paginates audit logs server side', function (): void {
    $user = auditApiUser(['audit-logs.viewAny']);

    foreach (range(1, 6) as $index) {
        createAuditLog([
            'description' => "Audit entry {$index}.",
            'created_at' => Carbon::parse("2026-04-01 0{$index}:00:00"),
            'updated_at' => Carbon::parse("2026-04-01 0{$index}:00:00"),
        ]);
    }

    $this->actingAs($user)
        ->getJson(route('api.admin.audit-logs.index', [
            'per_page' => 5,
            'page' => 2,
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.pagination.currentPage', 2)
        ->assertJsonPath('meta.pagination.perPage', 5)
        ->assertJsonPath('meta.pagination.total', 6);
});

it('sorts audit logs only by whitelisted fields', function (): void {
    $user = auditApiUser(['audit-logs.viewAny']);
    createAuditLog(['event' => 'oauth.token.revoked', 'created_at' => Carbon::parse('2026-04-01 09:00:00'), 'updated_at' => Carbon::parse('2026-04-01 09:00:00')]);
    createAuditLog(['event' => 'oauth.token.issued', 'created_at' => Carbon::parse('2026-04-01 10:00:00'), 'updated_at' => Carbon::parse('2026-04-01 10:00:00')]);

    $this->actingAs($user)
        ->getJson(route('api.admin.audit-logs.index', [
            'sort_field' => 'event_type',
            'sort_order' => 1,
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.eventType', 'oauth.token.issued')
        ->assertJsonPath('data.1.eventType', 'oauth.token.revoked');
});

it('returns audit log details for authorized admins', function (): void {
    $user = auditApiUser(['audit-logs.viewAny', 'audit-logs.view']);
    $actor = User::factory()->create(['name' => 'Audit Admin', 'email' => 'auditadmin@example.com']);
    $client = SsoClient::factory()->create(['name' => 'Portal', 'client_id' => 'portal']);
    $auditLog = createAuditLog([
        'causer_type' => User::class,
        'causer_id' => $actor->id,
        'subject_type' => SsoClient::class,
        'subject_id' => $client->id,
    ], [
        'client_id' => $client->id,
        'request_id' => 'req-123',
    ]);

    $this->actingAs($user)
        ->getJson(route('api.admin.audit-logs.show', $auditLog))
        ->assertOk()
        ->assertJsonPath('data.id', $auditLog->id)
        ->assertJsonPath('data.actor.display', 'Audit Admin (auditadmin@example.com)')
        ->assertJsonPath('data.client.clientId', 'portal')
        ->assertJsonPath('data.requestId', 'req-123');
});

it('sanitizes sensitive detail metadata recursively', function (): void {
    $user = auditApiUser(['audit-logs.viewAny', 'audit-logs.view']);
    $auditLog = createAuditLog([], [
        'request_context' => [
            'authorization' => 'Bearer super-secret',
            'nested' => [
                'access_token' => 'token-value',
                'safe' => 'visible',
            ],
        ],
        'client_secret' => 'never-show',
        'password' => 'bad',
    ]);

    $this->actingAs($user)
        ->getJson(route('api.admin.audit-logs.show', $auditLog))
        ->assertOk()
        ->assertJsonPath('data.meta.request_context.authorization', '[REDACTED]')
        ->assertJsonPath('data.meta.request_context.nested.access_token', '[REDACTED]')
        ->assertJsonPath('data.meta.client_secret', '[REDACTED]')
        ->assertJsonPath('data.meta.password', '[REDACTED]')
        ->assertJsonPath('data.meta.request_context.nested.safe', 'visible');
});

it('rejects unknown sort fields with validation instead of applying them', function (): void {
    $user = auditApiUser(['audit-logs.viewAny']);
    createAuditLog();

    $this->actingAs($user)
        ->getJson(route('api.admin.audit-logs.index', ['sort_field' => 'drop table activity_log']))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Validation failed.')
        ->assertJsonValidationErrors(['sort_field']);
});
