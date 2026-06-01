<?php

namespace App\Repositories;

use App\Models\SsoClient;
use App\Models\TokenPolicy;
use App\Repositories\Contracts\TokenPolicyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Prettus\Repository\Eloquent\Repository;

/**
 * OAuth token policy-k adminisztrációs adatkezeléséért felelős repository.
 *
 * A token policy határozza meg a tokenkiadás fő biztonsági paramétereit,
 * például az access/refresh token élettartamát, PKCE követelményeket,
 * refresh token rotációt és default policy viselkedést.
 */
class TokenPolicyRepository extends Repository implements TokenPolicyRepositoryInterface
{
    /**
     * Az admin token policy lista által támogatott rendezési mezők explicit leképezése.
     *
     * A frontendből érkező mezőnevek nem kerülnek közvetlenül SQL rendezésbe,
     * így a lista rendezése kontrollált és biztonságos marad.
     *
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

    /**
     * Lekérdezi az admin token policy listát kereséssel, státuszszűréssel és rendezéssel.
     *
     * Az admin felület számára láthatóvá teszi a tokenkiadási szabálycsomagokat,
     * hogy gyorsan ellenőrizhető legyen, mely policy-k aktívak és milyen
     * élettartam-paraméterekkel működnek.
     */
    public function paginateForAdminIndex(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator {
        $global = trim((string) ($filters['global'] ?? ''));
        $status = $filters['status'] ?? null;

        $column = $this->sortableFields[$sortField ?? 'name'] ?? $this->sortableFields['name'];
        $direction = ($sortOrder ?? 1) === -1 ? 'desc' : 'asc';

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
            ->when(
                $status !== null && $status !== '',
                fn ($query) => $query->where('is_active', filter_var($status, FILTER_VALIDATE_BOOL))
            )
            ->orderBy($column, $direction)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Létrehoz egy új token policy-t.
     *
     * A repository csak a mentést végzi; annak ellenőrzése, hogy a policy
     * értékei biztonságosak és üzletileg engedélyezettek-e, a validációs
     * és service réteg felelőssége.
     */
    public function createTokenPolicy(array $attributes): TokenPolicy
    {
        /** @var TokenPolicy $tokenPolicy */
        $tokenPolicy = $this->getModel()->newQuery()->create($attributes);

        return $tokenPolicy->refresh();
    }

    /**
     * Frissíti egy token policy adatait.
     *
     * A frissített modell visszatöltése biztosítja, hogy az admin felület
     * az aktuális adatbázisállapotot kapja vissza, például castolt boolean
     * és TTL mezőkkel együtt.
     */
    public function updateTokenPolicy(TokenPolicy $tokenPolicy, array $attributes): TokenPolicy
    {
        $tokenPolicy->update($attributes);

        return $tokenPolicy->refresh();
    }

    /**
     * Töröl egy token policy-t.
     *
     * A metódus feltételezi, hogy a hívó réteg már ellenőrizte, nem használja-e
     * aktív OAuth kliens az adott policy-t, illetve nem sérül-e default policy
     * követelmény.
     */
    public function deleteTokenPolicy(TokenPolicy $tokenPolicy): void
    {
        $tokenPolicy->delete();
    }

    /**
     * Tömeges műveletekhez betölti a megadott token policy rekordokat.
     *
     * Az admin megerősítő nézetek így nem csak az azonosítókat, hanem a tényleges
     * policy adatokat is meg tudják jeleníteni.
     *
     * @return Collection<int, TokenPolicy>
     */
    public function getByIds(array $ids): Collection
    {
        return $this->getModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * Tömegesen törli a megadott token policy-kat.
     *
     * A törölhetőségi döntést nem ez a metódus hozza meg; a service/policy rétegnek
     * kell megelőznie, hogy használatban lévő vagy default policy kerüljön törlésre.
     */
    public function deleteByIds(array $ids): void
    {
        $this->getModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->delete();
    }

    /**
     * Eltávolítja a default jelölést minden más token policy-ról.
     *
     * A rendszerben egyszerre csak egy aktív default token policy lehet üzletileg
     * értelmezhető. Ez a metódus az új vagy megtartandó default policy kivételével
     * letisztítja a korábbi default jelöléseket.
     */
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

    /**
     * Megszámolja, hogy a megadott token policy-kat hány OAuth kliens használja.
     *
     * Ez törlés, inaktiválás vagy policy módosítás előtt fontos hatáselemzés:
     * láthatóvá teszi, hogy egy biztonsági szabálycsomag változása hány
     * kliens tokenkiadási viselkedését érintené.
     *
     * @return array<int|string, int>
     */
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