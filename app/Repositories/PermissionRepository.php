<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Prettus\Repository\Eloquent\Repository;
use Spatie\Permission\Models\Permission;

/**
 * Jogosultságok adminisztrációs adatkezeléséért felelős repository.
 *
 * Ez a réteg a Spatie Permission modell köré ad projekt-specifikus
 * lekérdezéseket, különösen az admin lista, hozzárendelési ellenőrzések
 * és tömeges műveletek kiszolgálásához.
 */
class PermissionRepository extends Repository implements PermissionRepositoryInterface
{
    /**
     * Az admin jogosultságlista által támogatott rendezési mezők explicit leképezése.
     *
     * A frontendből érkező mezőnevek nem kerülnek közvetlenül SQL rendezésbe,
     * így a lista rendezése kontrollált és biztonságos marad.
     *
     * @var array<string, string>
     */
    private array $sortableFields = [
        'name' => 'name',
        'createdAt' => 'created_at',
    ];

    public function __construct(Permission $model)
    {
        parent::__construct($model);
    }

    /**
     * Lekérdezi az admin jogosultságlistát kereséssel, szűréssel és rendezéssel.
     *
     * A lista nem csak a jogosultságok nevét adja vissza, hanem azt is,
     * hány szerepkörhöz és közvetlen felhasználói hozzárendeléshez kapcsolódnak.
     * Ez segít az adminnak felmérni egy jogosultság törlési vagy módosítási
     * kockázatát.
     */
    public function paginateForAdminIndex(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator {
        $global = trim((string) ($filters['global'] ?? ''));
        $name = trim((string) ($filters['name'] ?? ''));

        $column = $this->sortableFields[$sortField ?? ''] ?? 'name';
        $direction = $sortOrder === -1 ? 'desc' : 'asc';

        return $this->getModel()
            ->newQuery()
            ->where('guard_name', 'web')
            ->withCount('roles')
            ->selectSub(
                DB::table('model_has_permissions')
                    ->selectRaw('count(*)')
                    ->whereColumn('permission_id', 'permissions.id')
                    ->where('model_type', User::class),
                'users_count',
            )
            ->when($global !== '', function ($query) use ($global): void {
                $query->where('name', 'like', "%{$global}%");
            })
            ->when($name !== '', fn ($query) => $query->where('name', 'like', "%{$name}%"))
            ->orderBy($column, $direction)
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * Létrehoz egy új jogosultságot.
     *
     * A repository csak a perzisztálásért felel; annak eldöntése, hogy az adott
     * jogosultságnév létrehozható-e, a validációs és szolgáltatási réteg feladata.
     */
    public function createPermission(array $attributes): Permission
    {
        /** @var Permission $permission */
        $permission = $this->getModel()->newQuery()->create($attributes);

        return $permission;
    }

    /**
     * Frissíti egy jogosultság adatait.
     *
     * A frissített modell visszatöltése biztosítja, hogy az admin felület
     * az aktuális adatbázisállapotot kapja vissza.
     */
    public function updatePermission(Permission $permission, array $attributes): Permission
    {
        $permission->fill($attributes);
        $permission->save();

        return $permission->refresh();
    }

    /**
     * Töröl egy jogosultságot.
     *
     * A metódus feltételezi, hogy a hívó réteg már ellenőrizte a törlés
     * üzleti feltételeit, például azt, hogy nincs aktív szerepkör- vagy
     * felhasználói hozzárendelés.
     */
    public function deletePermission(Permission $permission): void
    {
        $permission->delete();
    }

    /**
     * Ellenőrzi, hogy a jogosultsághoz tartozik-e bármilyen hozzárendelés.
     *
     * A jogosultság törlése előtt fontos tudni, hogy szerepkörök vagy
     * közvetlen felhasználói permission kapcsolatok használják-e még.
     * Ezzel megelőzhető jogosultsági konfigurációk véletlen megsértése.
     */
    public function hasAssignments(Permission $permission): bool
    {
        $permissionId = $permission->getKey();

        $hasRoleAssignments = DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->exists();

        if ($hasRoleAssignments) {
            return true;
        }

        return DB::table('model_has_permissions')
            ->where('permission_id', $permissionId)
            ->exists();
    }

    /**
     * Tömeges műveletekhez betölti a megadott jogosultságokat használati számlálókkal.
     *
     * A szerepkör- és felhasználói darabszámok segítenek az adminnak
     * megerősítés előtt látni, hogy a kiválasztott jogosultságok
     * ténylegesen használatban vannak-e.
     *
     * @return Collection<int, Permission>
     */
    public function getByIds(array $ids): Collection
    {
        /** @var Collection<int, Permission> $permissions */
        $permissions = $this->getModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->withCount('roles')
            ->selectSub(
                DB::table('model_has_permissions')
                    ->selectRaw('count(*)')
                    ->whereColumn('permission_id', 'permissions.id')
                    ->where('model_type', User::class),
                'users_count',
            )
            ->get();

        return $permissions;
    }

    /**
     * Tömegesen törli a megadott jogosultságokat.
     *
     * A metódus nem dönt törölhetőségről; ezt a service/policy rétegnek kell
     * előzetesen érvényesítenie, hogy ne sérüljön a jogosultsági modell.
     */
    public function deleteByIds(array $ids): void
    {
        $this->getModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->delete();
    }
}