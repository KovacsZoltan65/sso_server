<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Prettus\Repository\Eloquent\Repository;
use Spatie\Permission\Models\Role;

class UserRepository extends Repository implements UserRepositoryInterface
{
    /**
     * Az admin felhasználólista által támogatott rendezési mezők explicit leképezése.
     *
     * A frontendből érkező mezőnevek nem kerülnek közvetlenül SQL rendezésbe,
     * így a lista rendezése kontrollált és biztonságos marad.
     *
     * @var array<string, string>
     */
    private array $sortableFields = [
        'name' => 'name',
        'email' => 'email',
        'isActive' => 'is_active',
        'createdAt' => 'created_at',
        'emailVerifiedAt' => 'email_verified_at',
    ];

    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Lekérdezi az admin felhasználólistát kereséssel, szűréssel és rendezéssel.
     *
     * A lista célja, hogy az admin gyorsan áttekintse:
     * - kik használhatják a rendszert
     * - aktív vagy inaktív-e a fiókjuk
     * - megtörtént-e az email-verifikáció
     * - milyen szerepkörökkel rendelkeznek
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
        $email = trim((string) ($filters['email'] ?? ''));
        $status = $filters['status'] ?? null;
        $verified = $filters['verified'] ?? null;

        $column = $this->sortableFields[$sortField ?? ''] ?? 'name';
        $direction = $sortOrder === -1 ? 'desc' : 'asc';

        return $this->getModel()
            ->newQuery()
            ->with(['roles'])
            ->when($global !== '', function ($query) use ($global): void {
                $query->where(function ($innerQuery) use ($global): void {
                    $innerQuery
                        ->where('name', 'like', "%{$global}%")
                        ->orWhere('email', 'like', "%{$global}%");
                });
            })
            ->when($name !== '', fn ($query) => $query->where('name', 'like', "%{$name}%"))
            ->when($email !== '', fn ($query) => $query->where('email', 'like', "%{$email}%"))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($verified === 'verified', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when($verified === 'pending', fn ($query) => $query->whereNull('email_verified_at'))
            ->orderBy($column, $direction)
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * Visszaadja a rendszerben elérhető szerepkörök neveit.
     *
     * A lista felhasználó létrehozásakor és szerkesztésekor használható,
     * hogy az admin csak létező szerepkörök közül választhasson.
     *
     * @return Collection<int, string>
     */
    public function getRoleNames(): Collection
    {
        /** @var Collection<int, string> $roles */
        $roles = Role::query()
            ->orderBy('name')
            ->pluck('name');

        return $roles;
    }

    /**
     * Létrehoz egy új felhasználót és hozzárendeli a kiválasztott szerepköröket.
     *
     * A felhasználó és a jogosultsági kontextus egy műveletben áll elő,
     * így az admin felületen azonnal a tényleges szerepkörökkel jeleníthető meg.
     */
    public function createWithRoles(array $attributes, array $roles = []): User
    {
        /** @var User $user */
        $user = $this->getModel()->newQuery()->create($attributes);
        $user->syncRoles($roles);

        return $user->load('roles');
    }

    /**
     * Frissíti a felhasználó alapadatait és szerepköreit.
     *
     * A szerepkörök szinkronizálása teljes állapotként kezeli a beküldött listát,
     * ezért eltávolítja a már nem kijelölt szerepeket is.
     */
    public function updateWithRoles(User $user, array $attributes, array $roles = []): User
    {
        $user->fill($attributes);
        $user->save();
        $user->syncRoles($roles);

        return $user->load('roles');
    }

    /**
     * Frissíti a felhasználó saját profiladatait.
     *
     * Ez elkülönül az admin szerepkörkezeléstől, mert a profilfrissítés
     * nem módosíthat jogosultsági vagy biztonsági szerepkör adatokat.
     */
    public function updateProfile(User $user, array $attributes): User
    {
        $user->fill($attributes);
        $user->save();

        return $user->refresh();
    }

    /**
     * Frissíti a felhasználó jelszavát előre hash-elt értékkel.
     *
     * A repository nem végez jelszóhash-elést, mert az validációs vagy
     * szolgáltatási rétegbeli felelősség; itt csak a tárolás történik.
     */
    public function updatePassword(User $user, string $hashedPassword): User
    {
        $user->forceFill([
            'password' => $hashedPassword,
        ])->save();

        return $user->refresh();
    }

    /**
     * Visszatölti a felhasználó aktuális adatbázisállapotát.
     *
     * Hasznos olyan műveletek után, ahol timestamp, cast, observer vagy
     * adatbázisoldali változás miatt friss modellállapotra van szükség.
     */
    public function refreshUser(User $user): User
    {
        return $user->refresh();
    }

    /**
     * Tömeges műveletekhez betölti a megadott felhasználókat szerepköreikkel együtt.
     *
     * Az eager loading biztosítja, hogy admin megerősítő nézetekben vagy audit
     * folyamatokban ne keletkezzen N+1 lekérdezés.
     *
     * @return Collection<int, User>
     */
    public function getByIds(array $ids): Collection
    {
        /** @var Collection<int, User> $users */
        $users = $this->getModel()
            ->newQuery()
            ->with(['roles'])
            ->whereIn('id', $ids)
            ->get();

        return $users;
    }

    /**
     * Töröl egy felhasználói fiókot.
     *
     * A tényleges törlési szabályokat — például saját fiók védelme,
     * utolsó admin tiltása vagy kapcsolódó rekordok kezelése —
     * a hívó szolgáltatási/policy rétegnek kell érvényesítenie.
     */
    public function deleteUser(User $user): void
    {
        $user->delete();
    }

    /**
     * Tömegesen törli a megadott felhasználói fiókokat.
     *
     * A metódus feltételezi, hogy a hívó réteg már ellenőrizte,
     * mely felhasználók törölhetők biztonságosan.
     */
    public function deleteByIds(array $ids): void
    {
        $this->getModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->delete();
    }
}
