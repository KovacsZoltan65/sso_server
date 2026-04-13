<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ClientUserAccess;
use App\Models\SsoClient;
use App\Models\User;
use App\Repositories\Contracts\ClientUserAccessRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Prettus\Repository\Eloquent\Repository;

class ClientUserAccessRepository extends Repository implements ClientUserAccessRepositoryInterface
{
    /**
     * @var array<string, string>
     */
    private array $sortableFields = [
        'clientName' => 'sso_clients.name',
        'clientId' => 'sso_clients.client_id',
        'userName' => 'users.name',
        'userEmail' => 'users.email',
        'isActive' => 'client_user_access.is_active',
        'allowedFrom' => 'client_user_access.allowed_from',
        'allowedUntil' => 'client_user_access.allowed_until',
        'createdAt' => 'client_user_access.created_at',
    ];

    public function __construct(ClientUserAccess $model)
    {
        parent::__construct($model);
    }

    /**
     * @return class-string<ClientUserAccess>
     */
    public function model(): string
    {
        return ClientUserAccess::class;
    }

    /**
     * @param array $filters
     * @param mixed $sortField
     * @param mixed $sortOrder
     * @param int $perPage
     * @param int $page
     * @return LengthAwarePaginator
     */
    public function paginateForAdmin(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator {
        $global = trim((string) ($filters['global'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $clientId = $filters['client_id'] ?? null;
        $userId = $filters['user_id'] ?? null;

        $column = $this->sortableFields[$sortField ?? ''] ?? $this->sortableFields['createdAt'];
        $direction = $sortOrder === -1 ? 'desc' : 'asc';

        return $this->getModel()
            ->newQuery()
            ->select('client_user_access.*')
            ->join('sso_clients', 'sso_clients.id', '=', 'client_user_access.client_id')
            ->join('users', 'users.id', '=', 'client_user_access.user_id')
            ->with(['client', 'user'])
            ->when($global !== '', function (Builder $query) use ($global): void {
                $query->where(function (Builder $innerQuery) use ($global): void {
                    $innerQuery
                        ->where('sso_clients.name', 'like', "%{$global}%")
                        ->orWhere('sso_clients.client_id', 'like', "%{$global}%")
                        ->orWhere('users.name', 'like', "%{$global}%")
                        ->orWhere('users.email', 'like', "%{$global}%");
                });
            })
            ->when($status === 'active', fn (Builder $query) => $query->where('client_user_access.is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('client_user_access.is_active', false))
            ->when($clientId !== null, fn (Builder $query) => $query->where('client_user_access.client_id', (int) $clientId))
            ->when($userId !== null, fn (Builder $query) => $query->where('client_user_access.user_id', (int) $userId))
            ->orderBy($column, $direction)
            ->paginate($perPage, ['client_user_access.*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * @param int $id
     * @return ClientUserAccess|null
     */
    public function findById(int $id): ?ClientUserAccess
    {
        /** @var ClientUserAccess|null $access */
        $access = $this->getModel()
            ->newQuery()
            ->with(['client', 'user'])
            ->find($id);

        return $access;
    }

    /**
     * @param int $clientId
     * @param int $userId
     * @return ClientUserAccess|null
     */
    public function findAccessForClientAndUser(int $clientId, int $userId): ?ClientUserAccess
    {
        /** @var ClientUserAccess|null $access */
        $access = $this->getModel()
            ->newQuery()
            ->with(['client', 'user'])
            ->where('client_id', $clientId)
            ->where('user_id', $userId)
            ->first();

        return $access;
    }

    /**
     * @param int $clientId
     * @param int $userId
     * @return ClientUserAccess|null
     */
    public function findActiveAccessForClientAndUser(int $clientId, int $userId): ?ClientUserAccess
    {
        /** @var ClientUserAccess|null $access */
        $access = $this->getModel()
            ->newQuery()
            ->with(['client', 'user'])
            ->where('client_id', $clientId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        return $access;
    }

    /**
     * @param int $clientId
     * @return bool
     */
    public function clientHasAnyActiveRestrictions(int $clientId): bool
    {
        return $this->getModel()
            ->newQuery()
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @param array $attributes
     * @return ClientUserAccess
     */
    public function createAccess(array $attributes): ClientUserAccess
    {
        /** @var ClientUserAccess $access */
        $access = $this->getModel()->newQuery()->create($attributes);

        return $access->load(['client', 'user']);
    }

    /**
     * @param ClientUserAccess $access
     * @param array $attributes
     * @return ClientUserAccess
     */
    public function updateAccess(ClientUserAccess $access, array $attributes): ClientUserAccess
    {
        $access->fill($attributes);
        $access->save();

        return $access->refresh()->load(['client', 'user']);
    }

    /**
     * @param ClientUserAccess $access
     * @return void
     */
    public function deleteAccess(ClientUserAccess $access): void
    {
        $access->delete();
    }

    /**
     * @param array $ids
     * @return Collection<int, ClientUserAccess>
     */
    public function getByIds(array $ids): Collection
    {
        /** @var Collection<int, ClientUserAccess> $accesses */
        $accesses = $this->getModel()
            ->newQuery()
            ->with(['client', 'user'])
            ->whereIn('id', $ids)
            ->get();

        return $accesses;
    }

    /**
     * @param array $ids
     * @return void
     */
    public function deleteByIds(array $ids): void
    {
        $this->getModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->delete();
    }

    /**
     * @param int $clientId
     * @return Collection<int, ClientUserAccess>
     */
    public function listUsersForClient(int $clientId): Collection
    {
        /** @var Collection<int, ClientUserAccess> $accesses */
        $accesses = $this->getModel()
            ->newQuery()
            ->with('user')
            ->where('client_id', $clientId)
            ->latest('id')
            ->get();

        return $accesses;
    }

    /**
     * @param int $userId
     * @return Collection<int, ClientUserAccess>
     */
    public function listClientsForUser(int $userId): Collection
    {
        /** @var Collection<int, ClientUserAccess> $accesses */
        $accesses = $this->getModel()
            ->newQuery()
            ->with('client')
            ->where('user_id', $userId)
            ->latest('id')
            ->get();

        return $accesses;
    }

    /**
     * @return array<int, string, int>
     */
    public function clientOptions(): array
    {
        return SsoClient::query()
            ->orderBy('name')
            ->get(['id', 'name', 'client_id'])
            ->map(fn (SsoClient $client): array => [
                'id' => $client->id,
                'name' => $client->name,
                'clientId' => $client->client_id,
            ])
            ->values()
            ->all();
    }

    /**
     * Summary of userOptions
     * @return array<int, string, string, bool>
     */
    public function userOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'isActive' => (bool) $user->is_active,
            ])
            ->values()
            ->all();
    }
}
