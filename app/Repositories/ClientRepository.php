<?php

namespace App\Repositories;

use App\Models\SsoClient;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Prettus\Repository\Eloquent\Repository;

class ClientRepository extends Repository implements ClientRepositoryInterface
{
    /**
     * @var array<string, string>
     */
    private array $sortableFields = [
        'name' => 'name',
        'clientId' => 'client_id',
        'createdAt' => 'created_at',
    ];

    public function __construct(SsoClient $model)
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
        $name = trim((string) ($filters['name'] ?? ''));
        $status = $filters['status'] ?? null;

        if ($global !== '') {
            $query->where(function ($innerQuery) use ($global): void {
                $innerQuery
                    ->where('name', 'like', "%{$global}%")
                    ->orWhere('client_id', 'like', "%{$global}%");
            });
        }

        if ($name !== '') {
            $query->where('name', 'like', "%{$name}%");
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        }

        if ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $column = $this->sortableFields[$sortField ?? ''] ?? 'name';
        $direction = $sortOrder === -1 ? 'desc' : 'asc';

        $query->orderBy($column, $direction);

        return $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }

    public function createClient(array $attributes): SsoClient
    {
        /** @var SsoClient $client */
        $client = $this->getModel()->newQuery()->create($attributes);

        return $client;
    }

    public function updateClient(SsoClient $client, array $attributes): SsoClient
    {
        $client->fill($attributes);
        $client->save();

        return $client->refresh();
    }

    public function deleteClient(SsoClient $client): void
    {
        $client->delete();
    }
}
