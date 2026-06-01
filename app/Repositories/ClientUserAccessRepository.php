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
     * Az admin lista által támogatott rendezési mezők leképezése.
     *
     * A frontend által küldött oszlopneveket explicit adatbázis oszlopokra
     * fordítjuk, hogy elkerüljük a dinamikus orderBy használatát és a
     * nem várt oszlopnevekből eredő hibákat.
     *
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
     * Lekérdezi a kliens-felhasználó hozzáféréseket az admin lista számára.
     *
     * A metódus támogatja:
     * - globális keresést
     * - státusz alapú szűrést
     * - kliens szerinti szűrést
     * - felhasználó szerinti szűrést
     * - biztonságos rendezést előre definiált oszlopokon
     *
     * A kapcsolódó modellek eager loadinggal kerülnek betöltésre,
     * hogy elkerüljük az N+1 lekérdezési problémát.
     * 
     * @param  mixed  $sortField
     * @param  mixed  $sortOrder
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

    public function findById(int $id): ?ClientUserAccess
    {
        /** @var ClientUserAccess|null $access */
        $access = $this->getModel()
            ->newQuery()
            ->with(['client', 'user'])
            ->find($id);

        return $access;
    }

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
     * Megkeresi egy adott kliens és felhasználó aktív hozzáférési rekordját.
     *
     * Ez a metódus tipikusan OAuth autorizáció során használható annak
     * eldöntésére, hogy a felhasználó jogosult-e az adott kliens használatára.
     * 
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
     * Ellenőrzi, hogy a klienshez tartozik-e legalább egy aktív
     * felhasználói hozzáférési rekord.
     *
     * Az eredmény felhasználható annak eldöntésére, hogy a kliens
     * hozzáférései korlátozott üzemmódban működnek-e.
     * 
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
     * Létrehoz egy új kliens-felhasználó hozzáférési kapcsolatot.
     *
     * A visszatérő modell a kapcsolódó kliens és felhasználó adataival
     * együtt kerül visszaadásra, hogy további lekérdezés nélkül
     * felhasználható legyen API válaszokban.
     * 
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
     * Frissíti a meglévő hozzáférési rekordot és visszatölti annak
     * aktuális állapotát az adatbázisból.
     *
     * A refresh biztosítja, hogy minden adatbázis által módosított
     * mező (cast, trigger, timestamp stb.) naprakész legyen.
     */
    public function updateAccess(ClientUserAccess $access, array $attributes): ClientUserAccess
    {
        $access->fill($attributes);
        $access->save();

        return $access->refresh()->load(['client', 'user']);
    }

    public function deleteAccess(ClientUserAccess $access): void
    {
        $access->delete();
    }

    /**
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

    public function deleteByIds(array $ids): void
    {
        $this->getModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->delete();
    }

    /**
     * Visszaadja a klienshez rendelt felhasználókat hozzáférési rekordokkal együtt.
     *
     * Az eredmény admin felületeken és jogosultságkezelő nézeteken
     * használható a klienshez rendelt felhasználók megjelenítésére.
     * @return Collection<int, ClientUserAccess>
     */
    public function listUsersForClient(int $clientId): Collection
    {
        /** @var Collection<int, ClientUserAccess> $accesses */
        $accesses = $this->getModel()
            ->newQuery()
            ->with(['user'])
            ->where('client_id', $clientId)
            ->latest('id')
            ->get();

        return $accesses;
    }

    /**
     * Visszaadja az adott felhasználóhoz rendelt klienseket.
     *
     * Az eredmény alkalmas felhasználói hozzáférések áttekintésére,
     * audit célokra és admin kezelőfelületek kiszolgálására.
     * 
     * @return Collection<int, ClientUserAccess>
     */
    public function listClientsForUser(int $userId): Collection
    {
        /** @var Collection<int, ClientUserAccess> $accesses */
        $accesses = $this->getModel()
            ->newQuery()
            ->with(['client'])
            ->where('user_id', $userId)
            ->latest('id')
            ->get();

        return $accesses;
    }

    /**
     * @return array<int, array{
     *     id:int,
     *     name:string,
     *     clientId:string
     * }>
     * 
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
     * Felhasználóválasztó listához szükséges adatok.
     *
     * @return array<int, array{
     *     id:int,
     *     name:string,
     *     email:string,
     *     isActive:bool
     * }>
     * 
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
