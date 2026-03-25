<?php

namespace App\Repositories;

use App\Models\SsoClient;
use App\Models\TokenPolicy;
use App\Repositories\Contracts\TokenPolicyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Prettus\Repository\Eloquent\Repository;

class TokenPolicyRepository extends Repository implements TokenPolicyRepositoryInterface
{
    /**
     * @var array<string, string>
     */
    private array $sortableFields = [
        'name' => 'name',
        'code' => 'code',
        'accessTokenTtlMinutes' => 'access_token_ttl_minutes',
        'refreshTokenTtlMinutes' => 'refresh_token_ttl_minutes',
        'createdAt' => 'created_at',
    ];

    public function __construct(TokenPolicy $model)
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
        $query = $this->getModel()->newQuery();

        $global = trim((string) ($filters['global'] ?? ''));
        $status = $filters['status'] ?? null;

        if ($global !== '') {
            $query->where(function ($innerQuery) use ($global): void {
                $innerQuery
                    ->where('name', 'like', "%{$global}%")
                    ->orWhere('code', 'like', "%{$global}%")
                    ->orWhere('description', 'like', "%{$global}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', filter_var($status, FILTER_VALIDATE_BOOL));
        }

        $column = $this->sortableFields[$sortField ?? 'name'] ?? $this->sortableFields['name'];
        $direction = ($sortOrder ?? 1) === -1 ? 'desc' : 'asc';

        return $query
            ->orderBy($column, $direction)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function createTokenPolicy(array $attributes): TokenPolicy
    {
        /** @var TokenPolicy $tokenPolicy */
        $tokenPolicy = $this->getModel()->newQuery()->create($attributes);

        return $tokenPolicy->refresh();
    }

    public function updateTokenPolicy(TokenPolicy $tokenPolicy, array $attributes): TokenPolicy
    {
        $tokenPolicy->update($attributes);

        return $tokenPolicy->refresh();
    }

    public function deleteTokenPolicy(TokenPolicy $tokenPolicy): void
    {
        $tokenPolicy->delete();
    }

    public function getByIds(array $ids): Collection
    {
        return $this->getModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->get();
    }

    public function deleteByIds(array $ids): void
    {
        $this->getModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->delete();
    }

    public function clearDefaultFlagExcept(?int $exceptId = null): void
    {
        $query = $this->getModel()
            ->newQuery()
            ->where('is_default', true);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        $query->update(['is_default' => false]);
    }

    public function clientUsageCounts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return SsoClient::query()
            ->whereIn('token_policy_id', $ids)
            ->selectRaw('token_policy_id, COUNT(*) as aggregate')
            ->groupBy('token_policy_id')
            ->pluck('aggregate', 'token_policy_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }
}
