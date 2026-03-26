<?php

namespace App\Repositories;

use App\Models\Scope;
use App\Models\SsoClient;
use App\Repositories\Contracts\ScopeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Prettus\Repository\Eloquent\Repository;

class ScopeRepository extends Repository implements ScopeRepositoryInterface
{
    /**
     * @var array<string, string>
     */
    private array $sortableFields = [
        'name' => 'name',
        'code' => 'code',
        'createdAt' => 'created_at',
    ];

    public function __construct(Scope $model)
    {
        parent::__construct($model);
    }

    public function paginateForAdminIndex(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator {
        $global = trim((string) ($filters['global'] ?? ''));
        $name = trim((string) ($filters['name'] ?? ''));
        $code = trim((string) ($filters['code'] ?? ''));
        $status = $filters['status'] ?? null;

        $column = $this->sortableFields[$sortField ?? ''] ?? 'name';
        $direction = $sortOrder === -1 ? 'desc' : 'asc';

        return $this->getModel()
            ->newQuery()
            ->when($global !== '', function ($query) use ($global): void {
                $query->where(function ($innerQuery) use ($global): void {
                    $innerQuery
                        ->where('name', 'like', "%{$global}%")
                        ->orWhere('code', 'like', "%{$global}%")
                        ->orWhere('description', 'like', "%{$global}%");
                });
            })
            ->when($name !== '', fn ($query) => $query->where('name', 'like', "%{$name}%"))
            ->when($code !== '', fn ($query) => $query->where('code', 'like', "%{$code}%"))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy($column, $direction)
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    public function createScope(array $attributes): Scope
    {
        /** @var Scope $scope */
        $scope = $this->getModel()->newQuery()->create($attributes);

        return $scope;
    }

    public function updateScope(Scope $scope, array $attributes): Scope
    {
        $scope->fill($attributes);
        $scope->save();

        return $scope->refresh();
    }

    public function deleteScope(Scope $scope): void
    {
        $scope->delete();
    }

    public function getByIds(array $ids): Collection
    {
        /** @var Collection<int, Scope> $scopes */
        $scopes = $this->getModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->get();

        return $scopes;
    }

    public function deleteByIds(array $ids): void
    {
        $this->getModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->delete();
    }

    public function clientUsageCounts(array $codes): array
    {
        $codes = array_values(array_unique(array_filter($codes)));

        if ($codes === []) {
            return [];
        }

        $counts = array_fill_keys($codes, 0);

        $clients = SsoClient::query()
            ->select(['id', 'scopes'])
            ->where(function ($query) use ($codes): void {
                foreach ($codes as $code) {
                    $query->orWhereJsonContains('scopes', $code);
                }
            })
            ->get();

        foreach ($clients as $client) {
            foreach (array_unique($client->scopes ?? []) as $scopeCode) {
                if (array_key_exists($scopeCode, $counts)) {
                    $counts[$scopeCode]++;
                }
            }
        }

        return $counts;
    }
}
