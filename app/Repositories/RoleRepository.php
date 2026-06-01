<?php

namespace App\Repositories;

use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Prettus\Repository\Eloquent\Repository;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Szerepkörök adminisztrációs adatkezeléséért felelős repository.
 *
 * Ez a réteg a Spatie Role modell köré ad projekt-specifikus
 * lekérdezéseket az admin lista, jogosultság-hozzárendelés,
 * használati ellenőrzés és tömeges műveletek kiszolgálásához.
 */
class RoleRepository extends Repository implements RoleRepositoryInterface
{
    /**
     * Az admin szerepkörlista által támogatott rendezési mezők explicit leképezése.
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

    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

    /**
     * Lekérdezi az admin szerepkörlistát kereséssel, szűréssel és rendezéssel.
     *
     * A lista a szerepkörök mellett visszaadja a kapcsolódó jogosultságokat,
     * valamint a jogosultság- és felhasználószámokat is. Ez segít felmérni,
     * hogy egy szerepkör módosítása vagy törlése mekkora hatással járhat.
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
            ->with(['permissions:id,name'])
            ->withCount(['permissions', 'users'])
            ->when($global !== '', function ($query) use ($global): void {
                $query->where('name', 'like', "%{$global}%");
            })
            ->when($name !== '', fn ($query) => $query->where('name', 'like', "%{$name}%"))
            ->orderBy($column, $direction)
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * Visszaadja a web guard alatt elérhető jogosultságneveket.
     *
     * Szerepkör létrehozásakor és szerkesztésekor ez biztosítja,
     * hogy az admin csak a releváns guardhoz tartozó jogosultságokat
     * tudja a szerepkörhöz rendelni.
     *
     * @return Collection<int, string>
     */
    public function getPermissionNames(): Collection
    {
        /** @var Collection<int, string> $permissions */
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name');

        return $permissions;
    }

    /**
     * Létrehoz egy új szerepkört és hozzárendeli a kiválasztott jogosultságokat.
     *
     * A szerepkör és jogosultsági kontextusa egy műveletben áll elő,
     * így az admin felület azonnal a tényleges permission állapotot kapja vissza.
     */
    public function createRole(array $attributes, array $permissions = []): Role
    {
        /** @var Role $role */
        $role = $this->getModel()->newQuery()->create($attributes);
        $role->syncPermissions($permissions);

        return $role->load('permissions');
    }

    /**
     * Frissíti a szerepkör alapadatait és jogosultságait.
     *
     * A jogosultságok szinkronizálása teljes állapotként kezeli a beküldött
     * listát, ezért eltávolítja a már nem kijelölt permission kapcsolatokat is.
     */
    public function updateRole(Role $role, array $attributes, array $permissions = []): Role
    {
        $role->fill($attributes);
        $role->save();
        $role->syncPermissions($permissions);

        return $role->load('permissions');
    }

    /**
     * Töröl egy szerepkört.
     *
     * A metódus feltételezi, hogy a hívó réteg már ellenőrizte a törlés
     * üzleti feltételeit, például azt, hogy nincs hozzárendelt felhasználó.
     */
    public function deleteRole(Role $role): void
    {
        $role->delete();
    }

    /**
     * Ellenőrzi, hogy a szerepkör hozzá van-e rendelve bármely felhasználóhoz.
     *
     * Törlés előtt fontos üzleti védelmi pont, mert aktív felhasználók
     * szerepkörének eltávolítása jogosultsági kiesést vagy adminisztrációs
     * inkonzisztenciát okozhat.
     */
    public function hasAssignedUsers(Role $role): bool
    {
        return $role->users()->exists();
    }

    /**
     * Tömeges műveletekhez betölti a megadott szerepköröket kapcsolódó adatokkal.
     *
     * A jogosultság- és felhasználószámok segítenek az adminnak megerősítés előtt
     * látni, hogy a kiválasztott szerepkörök ténylegesen használatban vannak-e.
     *
     * @return Collection<int, Role>
     */
    public function getByIds(array $ids): Collection
    {
        /** @var Collection<int, Role> $roles */
        $roles = $this->getModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->with(['permissions:id,name'])
            ->withCount(['permissions', 'users'])
            ->get();

        return $roles;
    }

    /**
     * Tömegesen törli a megadott szerepköröket.
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