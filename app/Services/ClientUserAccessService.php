<?php

namespace App\Services;

use App\Data\ClientUserAccessSummaryData;
use App\Models\ClientUserAccess;
use App\Models\SsoClient;
use App\Models\User;
use App\Repositories\Contracts\ClientUserAccessRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Support\Permissions\ClientUserAccessPermissions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * @phpstan-type AccessDecision array{
 *     allowed: bool,
 *     decision: string,
 *     reason: string,
 *     restricted: bool,
 *     access: ClientUserAccess|null,
 *     allowed_from: string|null,
 *     allowed_until: string|null
 * }
 */
class ClientUserAccessService
{
    public function __construct(
        private readonly ClientUserAccessRepositoryInterface $accesses,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getIndexPayload(
        array $filters,
        int $perPage = 10,
        ?string $sortField = null,
        ?int $sortOrder = null,
        int $page = 1,
    ): array {
        $paginator = $this->accesses->paginateForAdmin($filters, $sortField, $sortOrder, $perPage, $page);
        $canDelete = auth()->user()?->can(ClientUserAccessPermissions::DELETE)
            || auth()->user()?->can(ClientUserAccessPermissions::DELETE_ANY)
            || false;

        return [
            'rows' => Collection::make($paginator->items())
                ->map(fn (ClientUserAccess $access) => ClientUserAccessSummaryData::fromModel($access, $canDelete))
                ->values()
                ->all(),
            'clientOptions' => $this->accesses->clientOptions(),
            'userOptions' => $this->accesses->userOptions(),
            'canManageClientAccess' => auth()->user()?->can(ClientUserAccessPermissions::CREATE)
                || auth()->user()?->can(ClientUserAccessPermissions::UPDATE)
                || auth()->user()?->can(ClientUserAccessPermissions::DELETE)
                || auth()->user()?->can(ClientUserAccessPermissions::DELETE_ANY)
                || false,
            'filters' => [
                'global' => $filters['global'] ?? null,
                'client_id' => $filters['client_id'] ?? null,
                'user_id' => $filters['user_id'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
            'sorting' => [
                'field' => $sortField ?? 'createdAt',
                'order' => $sortOrder ?? -1,
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'first' => ($paginator->currentPage() - 1) * $paginator->perPage(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createAccess(array $payload): ClientUserAccess
    {
        $this->guardDuplicate((int) $payload['client_id'], (int) $payload['user_id']);

        return DB::transaction(function () use ($payload): ClientUserAccess {
            $access = $this->accesses->createAccess($this->sanitizePayload($payload));

            $this->auditLogService->logAdminCrud(
                resource: 'client_user_access',
                action: 'created',
                description: 'Client user access created.',
                subject: $access->client,
                causer: auth()->user(),
                properties: $this->auditProperties($access, 'granted'),
            );

            return $access;
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateAccess(ClientUserAccess $access, array $payload): ClientUserAccess
    {
        $this->guardDuplicate((int) $payload['client_id'], (int) $payload['user_id'], $access->id);

        return DB::transaction(function () use ($access, $payload): ClientUserAccess {
            $updatedAccess = $this->accesses->updateAccess($access, $this->sanitizePayload($payload));

            $this->auditLogService->logAdminCrud(
                resource: 'client_user_access',
                action: 'updated',
                description: 'Client user access updated.',
                subject: $updatedAccess->client,
                causer: auth()->user(),
                properties: $this->auditProperties($updatedAccess, 'updated'),
            );

            return $updatedAccess;
        });
    }

    public function deleteAccess(ClientUserAccess $access): void
    {
        $this->auditLogService->logAdminCrud(
            resource: 'client_user_access',
            action: 'deleted',
            description: 'Client user access deleted.',
            subject: $access->client,
            causer: auth()->user(),
            properties: $this->auditProperties($access, 'deleted'),
        );

        $this->accesses->deleteAccess($access);
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    public function bulkDelete(array $ids): array
    {
        $accesses = $this->accesses->getByIds($ids);

        if ($accesses->count() !== count($ids)) {
            throw new RuntimeException('One or more selected access records could not be found.');
        }

        foreach ($accesses as $access) {
            $this->auditLogService->logAdminCrud(
                resource: 'client_user_access',
                action: 'deleted',
                description: 'Client user access deleted.',
                subject: $access->client,
                causer: auth()->user(),
                properties: $this->auditProperties($access, 'deleted'),
            );
        }

        $this->accesses->deleteByIds($ids);

        return $ids;
    }

    public function canUserAccessClient(User $user, SsoClient $client): bool
    {
        return $this->evaluateUserAccess($user, $client)['allowed'];
    }

    /**
     * @return AccessDecision
     */
    public function evaluateUserAccess(User $user, SsoClient $client, ?Carbon $at = null): array
    {
        $now = $at ?? now();

        if (! (bool) $user->is_active) {
            return $this->buildDecision(false, 'denied', 'inactive_user');
        }

        if (! (bool) $client->is_active) {
            return $this->buildDecision(false, 'denied', 'inactive_client');
        }

        $restricted = $this->accesses->clientHasAnyActiveRestrictions($client->id);

        if (! $restricted) {
            return $this->buildDecision(true, 'allowed', 'open_client', false);
        }

        $access = $this->accesses->findActiveAccessForClientAndUser($client->id, $user->id);

        if (! $access instanceof ClientUserAccess) {
            return $this->buildDecision(false, 'denied', 'missing_active_access', true);
        }

        if ($access->allowed_from !== null && $now->lt($access->allowed_from)) {
            return $this->buildDecision(false, 'denied', 'before_allowed_from', true, $access);
        }

        if ($access->allowed_until !== null && $now->gt($access->allowed_until)) {
            return $this->buildDecision(false, 'denied', 'after_allowed_until', true, $access);
        }

        return $this->buildDecision(true, 'allowed', 'explicit_access', true, $access);
    }

    /**
     * @return Collection<int, ClientUserAccess>
     */
    public function listUsersForClient(SsoClient $client): Collection
    {
        return $this->accesses->listUsersForClient($client->id);
    }

    /**
     * @return Collection<int, ClientUserAccess>
     */
    public function listClientsForUser(User $user): Collection
    {
        return $this->accesses->listClientsForUser($user->id);
    }

    public function findById(int $id): ?ClientUserAccess
    {
        return $this->accesses->findById($id);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        return [
            'client_id' => (int) $payload['client_id'],
            'user_id' => (int) $payload['user_id'],
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'allowed_from' => $payload['allowed_from'] ?? null,
            'allowed_until' => $payload['allowed_until'] ?? null,
            'notes' => isset($payload['notes']) ? trim((string) $payload['notes']) : null,
        ];
    }

    private function guardDuplicate(int $clientId, int $userId, ?int $ignoreId = null): void
    {
        $existing = $this->accesses->findAccessForClientAndUser($clientId, $userId);

        if (! $existing instanceof ClientUserAccess) {
            return;
        }

        if ($ignoreId !== null && $existing->id === $ignoreId) {
            return;
        }

        throw ValidationException::withMessages([
            'user_id' => 'This user already has an access record for the selected client.',
        ]);
    }

    /**
     * @return AccessDecision
     */
    private function buildDecision(
        bool $allowed,
        string $decision,
        string $reason,
        bool $restricted = false,
        ?ClientUserAccess $access = null,
    ): array {
        return [
            'allowed' => $allowed,
            'decision' => $decision,
            'reason' => $reason,
            'restricted' => $restricted,
            'access' => $access,
            'allowed_from' => $access?->allowed_from?->toIso8601String(),
            'allowed_until' => $access?->allowed_until?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditProperties(ClientUserAccess $access, string $decision): array
    {
        return [
            'client_id' => $access->client_id,
            'target_user_id' => $access->user_id,
            'client_access_id' => $access->id,
            'decision' => $decision,
            'status' => $access->is_active ? 'active' : 'inactive',
            'allowed_from' => $access->allowed_from?->toIso8601String(),
            'allowed_until' => $access->allowed_until?->toIso8601String(),
        ];
    }
}
