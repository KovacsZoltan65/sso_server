<?php

namespace App\Services;

use App\Data\ScopeSummaryData;
use App\Models\Scope;
use App\Repositories\Contracts\ScopeRepositoryInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;

class ScopeService
{
    public function __construct(
        private readonly ScopeRepositoryInterface $scopes,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getIndexPayload(
        array $filters,
        int $perPage = 10,
        ?string $sortField = null,
        ?int $sortOrder = null,
        int $page = 1,
    ): array {
        $paginator = $this->scopes->paginateForAdminIndex($filters, $sortField, $sortOrder, $perPage, $page);
        $scopes = Collection::make($paginator->items());
        $usageCounts = $this->scopes->clientUsageCounts(
            $scopes->pluck('code')->filter()->values()->all(),
        );

        return [
            'rows' => $scopes
                ->map(fn (Scope $scope) => ScopeSummaryData::fromModel(
                    scope: $scope,
                    clientsCount: $usageCounts[$scope->code] ?? 0,
                    canDelete: $this->canDeleteScope($scope, $usageCounts[$scope->code] ?? 0),
                    deleteBlockCode: $this->deleteBlockCode($scope, $usageCounts[$scope->code] ?? 0),
                    deleteBlockReason: $this->deleteBlockReason($scope, $usageCounts[$scope->code] ?? 0),
                ))
                ->values()
                ->all(),
            'canManageScopes' => auth()->user()?->can('scopes.create')
                || auth()->user()?->can('scopes.update')
                || auth()->user()?->can('scopes.delete')
                || auth()->user()?->can('scopes.deleteAny')
                || auth()->user()?->can('scopes.manage')
                || false,
            'filters' => [
                'global' => $filters['global'] ?? null,
                'name' => $filters['name'] ?? null,
                'code' => $filters['code'] ?? null,
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
    public function getEditPayload(Scope $scope): array
    {
        return [
            'scope' => [
                'id' => $scope->id,
                'name' => $scope->name,
                'code' => $scope->code,
                'description' => $scope->description,
                'isActive' => (bool) $scope->is_active,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createScope(array $payload): Scope
    {
        return $this->scopes->createScope($this->normalizedAttributes($payload));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateScope(Scope $scope, array $payload): Scope
    {
        $attributes = $this->normalizedAttributes($payload);

        if (($attributes['code'] ?? $scope->code) !== $scope->code && $this->isInUse($scope)) {
            throw new RuntimeException('This scope is assigned to clients and its code cannot be changed.');
        }

        return $this->scopes->updateScope($scope, $attributes);
    }

    public function deleteScope(Scope $scope): void
    {
        $this->guardDeleteable($scope);

        $this->scopes->deleteScope($scope);
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    public function bulkDeleteScopes(array $ids): array
    {
        $scopes = $this->scopes->getByIds($ids);

        if ($scopes->count() !== count($ids)) {
            throw new RuntimeException('One or more selected scopes could not be found.');
        }

        $usageCounts = $this->scopes->clientUsageCounts($scopes->pluck('code')->values()->all());

        foreach ($scopes as $scope) {
            $this->guardDeleteable($scope, $usageCounts[$scope->code] ?? 0);
        }

        $deletedIds = $scopes->pluck('id')->values()->all();

        $this->scopes->deleteByIds($deletedIds);

        return $deletedIds;
    }

    public function canDeleteScope(Scope $scope, ?int $clientUsageCount = null): bool
    {
        return $this->deleteBlockCode($scope, $clientUsageCount) === null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizedAttributes(array $payload): array
    {
        return [
            ...Arr::only($payload, ['name', 'is_active']),
            'code' => trim((string) ($payload['code'] ?? '')),
            'description' => $this->normalizedDescription($payload['description'] ?? null),
        ];
    }

    private function normalizedDescription(mixed $description): ?string
    {
        $value = trim((string) ($description ?? ''));

        return $value === '' ? null : $value;
    }

    private function guardDeleteable(Scope $scope, ?int $clientUsageCount = null): void
    {
        $reason = $this->deleteBlockReason($scope, $clientUsageCount);

        if ($reason !== null) {
            throw new RuntimeException($reason);
        }
    }

    private function deleteBlockCode(Scope $scope, ?int $clientUsageCount = null): ?string
    {
        $usageCount = $clientUsageCount ?? $this->usageCount($scope);

        if ($usageCount > 0) {
            return 'assigned_clients';
        }

        return null;
    }

    private function deleteBlockReason(Scope $scope, ?int $clientUsageCount = null): ?string
    {
        return match ($this->deleteBlockCode($scope, $clientUsageCount)) {
            'assigned_clients' => 'This scope is assigned to clients and cannot be deleted.',
            default => null,
        };
    }

    private function isInUse(Scope $scope): bool
    {
        return $this->usageCount($scope) > 0;
    }

    private function usageCount(Scope $scope): int
    {
        return $this->scopes->clientUsageCounts([$scope->code])[$scope->code] ?? 0;
    }
}
