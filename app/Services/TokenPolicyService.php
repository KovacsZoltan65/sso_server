<?php

namespace App\Services;

use App\Data\TokenPolicySummaryData;
use App\Models\TokenPolicy;
use App\Repositories\Contracts\TokenPolicyRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Support\Permissions\TokenPolicyPermissions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TokenPolicyService
{
    public function __construct(
        private readonly TokenPolicyRepositoryInterface $tokenPolicies,
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
        $paginator = $this->tokenPolicies->paginateForAdminIndex($filters, $sortField, $sortOrder, $perPage, $page);
        $clientUsageCounts = $this->tokenPolicies->clientUsageCounts(
            collect($paginator->items())->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        return [
            'rows' => Collection::make($paginator->items())
                ->map(function (TokenPolicy $tokenPolicy) use ($clientUsageCounts) {
                    $clientsCount = $clientUsageCounts[$tokenPolicy->id] ?? 0;
                    [$canDelete, $deleteBlockCode, $deleteBlockReason] = $this->deleteState($tokenPolicy, $clientsCount);

                    return TokenPolicySummaryData::fromModel(
                        tokenPolicy: $tokenPolicy,
                        clientsCount: $clientsCount,
                        canDelete: $canDelete,
                        deleteBlockCode: $deleteBlockCode,
                        deleteBlockReason: $deleteBlockReason,
                    );
                })
                ->values()
                ->all(),
            'canManageTokenPolicies' => auth()->user()?->can(TokenPolicyPermissions::CREATE)
                || auth()->user()?->can(TokenPolicyPermissions::UPDATE)
                || auth()->user()?->can(TokenPolicyPermissions::DELETE)
                || auth()->user()?->can(TokenPolicyPermissions::DELETE_ANY)
                || false,
            'filters' => [
                'global' => $filters['global'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
            'sorting' => [
                'field' => $sortField ?? 'name',
                'order' => $sortOrder ?? 1,
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
     * @return array<string, mixed>
     */
    public function getCreatePayload(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getEditPayload(TokenPolicy $tokenPolicy): array
    {
        return [
            'tokenPolicy' => [
                'id' => $tokenPolicy->id,
                'name' => $tokenPolicy->name,
                'code' => $tokenPolicy->code,
                'description' => $tokenPolicy->description,
                'access_token_ttl_minutes' => (int) $tokenPolicy->access_token_ttl_minutes,
                'refresh_token_ttl_minutes' => (int) $tokenPolicy->refresh_token_ttl_minutes,
                'refresh_token_rotation_enabled' => (bool) $tokenPolicy->refresh_token_rotation_enabled,
                'pkce_required' => (bool) $tokenPolicy->pkce_required,
                'reuse_refresh_token_forbidden' => (bool) $tokenPolicy->reuse_refresh_token_forbidden,
                'is_default' => (bool) $tokenPolicy->is_default,
                'is_active' => (bool) $tokenPolicy->is_active,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createTokenPolicy(array $payload): TokenPolicy
    {
        return DB::transaction(function () use ($payload): TokenPolicy {
            $attributes = $this->normalizePayload($payload);

            if ($attributes['is_default']) {
                $this->tokenPolicies->clearDefaultFlagExcept();
            }

            $tokenPolicy = $this->tokenPolicies->createTokenPolicy($attributes);

            $this->auditLogService->logAdminCrud(
                resource: 'token_policy',
                action: 'created',
                description: 'Token policy created.',
                subject: $tokenPolicy,
                causer: auth()->user(),
                properties: [
                    'policy_id' => $tokenPolicy->id,
                    'updated_fields' => array_keys($attributes),
                    'status' => $tokenPolicy->is_active ? 'active' : 'inactive',
                ],
            );

            return $tokenPolicy;
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateTokenPolicy(TokenPolicy $tokenPolicy, array $payload): TokenPolicy
    {
        return DB::transaction(function () use ($tokenPolicy, $payload): TokenPolicy {
            $attributes = $this->normalizePayload($payload);

            if ($attributes['is_default']) {
                $this->tokenPolicies->clearDefaultFlagExcept($tokenPolicy->id);
            }

            $updatedPolicy = $this->tokenPolicies->updateTokenPolicy($tokenPolicy, $attributes);

            $this->auditLogService->logAdminCrud(
                resource: 'token_policy',
                action: 'updated',
                description: 'Token policy updated.',
                subject: $updatedPolicy,
                causer: auth()->user(),
                properties: [
                    'policy_id' => $updatedPolicy->id,
                    'updated_fields' => array_keys($attributes),
                    'status' => $updatedPolicy->is_active ? 'active' : 'inactive',
                ],
            );

            return $updatedPolicy;
        });
    }

    public function deleteTokenPolicy(TokenPolicy $tokenPolicy): void
    {
        $clientsCount = $this->tokenPolicies->clientUsageCounts([$tokenPolicy->id])[$tokenPolicy->id] ?? 0;
        $this->assertCanDelete($tokenPolicy, $clientsCount);

        $this->auditLogService->logAdminCrud(
            resource: 'token_policy',
            action: 'deleted',
            description: 'Token policy deleted.',
            subject: $tokenPolicy,
            causer: auth()->user(),
            properties: [
                'policy_id' => $tokenPolicy->id,
            ],
        );

        $this->tokenPolicies->deleteTokenPolicy($tokenPolicy);
    }

    /**
     * @param array<int, int> $ids
     * @return array<string, array<string, int>>
     */
    public function bulkDeleteTokenPolicies(array $ids): array
    {
        $tokenPolicies = $this->tokenPolicies->getByIds($ids);
        $clientUsageCounts = $this->tokenPolicies->clientUsageCounts($ids);

        foreach ($tokenPolicies as $tokenPolicy) {
            $this->assertCanDelete($tokenPolicy, $clientUsageCounts[$tokenPolicy->id] ?? 0);
        }

        foreach ($tokenPolicies as $tokenPolicy) {
            $this->auditLogService->logAdminCrud(
                resource: 'token_policy',
                action: 'deleted',
                description: 'Token policy deleted.',
                subject: $tokenPolicy,
                causer: auth()->user(),
                properties: [
                    'policy_id' => $tokenPolicy->id,
                ],
            );
        }

        $this->tokenPolicies->deleteByIds($ids);

        return [
            'meta' => [
                'deletedCount' => count($ids),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        return [
            'name' => trim((string) $payload['name']),
            'code' => trim((string) $payload['code']),
            'description' => $payload['description'] !== null ? trim((string) $payload['description']) : null,
            'access_token_ttl_minutes' => (int) $payload['access_token_ttl_minutes'],
            'refresh_token_ttl_minutes' => (int) $payload['refresh_token_ttl_minutes'],
            'refresh_token_rotation_enabled' => (bool) $payload['refresh_token_rotation_enabled'],
            'pkce_required' => (bool) $payload['pkce_required'],
            'reuse_refresh_token_forbidden' => (bool) $payload['reuse_refresh_token_forbidden'],
            'is_default' => (bool) $payload['is_default'],
            'is_active' => (bool) $payload['is_default'] ? true : (bool) $payload['is_active'],
        ];
    }

    /**
     * @return array{0: bool, 1: string|null, 2: string|null}
     */
    private function deleteState(TokenPolicy $tokenPolicy, int $clientsCount): array
    {
        if ($tokenPolicy->is_default) {
            return [false, 'default_policy', 'The default token policy cannot be deleted. Assign another default policy first.'];
        }

        if ($clientsCount > 0) {
            return [false, 'assigned_clients', 'This token policy is assigned to clients and cannot be deleted.'];
        }

        return [true, null, null];
    }

    private function assertCanDelete(TokenPolicy $tokenPolicy, int $clientsCount): void
    {
        if ($tokenPolicy->is_default) {
            throw new RuntimeException('The default token policy cannot be deleted. Assign another default policy first.');
        }

        if ($clientsCount > 0) {
            throw new RuntimeException('This token policy is assigned to clients and cannot be deleted.');
        }
    }
}
