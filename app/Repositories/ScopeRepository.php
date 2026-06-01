<?php

namespace App\Repositories;

use App\Models\Scope;
use App\Models\SsoClient;
use App\Repositories\Contracts\ScopeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Prettus\Repository\Eloquent\Repository;

/**
 * OAuth scope-ok adminisztrációs adatkezeléséért felelős repository.
 *
 * Ez a réteg a scope törzsadatok listázását, létrehozását, módosítását,
 * törlését és klienshasználati ellenőrzését szolgálja ki.
 */
class ScopeRepository extends Repository implements ScopeRepositoryInterface
{
    /**
     * Az admin scope lista által támogatott rendezési mezők explicit leképezése.
     *
     * A frontendből érkező mezőnevek nem kerülnek közvetlenül SQL rendezésbe,
     * így a lista rendezése kontrollált és biztonságos marad.
     *
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

    /**
     * Lekérdezi az admin scope listát kereséssel, szűréssel és rendezéssel.
     *
     * A scope-ok OAuth jogosultsági határokat jelölnek, ezért az admin felületnek
     * gyorsan kereshetővé kell tennie a nevüket, kódjukat, leírásukat és aktív
     * állapotukat.
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

    /**
     * Létrehoz egy új OAuth scope-ot.
     *
     * A repository csak a perzisztálásért felel; annak eldöntése, hogy a scope
     * kód üzletileg elfogadható és egyedi-e, a validációs/service réteg feladata.
     */
    public function createScope(array $attributes): Scope
    {
        /** @var Scope $scope */
        $scope = $this->getModel()->newQuery()->create($attributes);

        return $scope;
    }

    /**
     * Frissíti egy scope törzsadatait.
     *
     * A frissített modell visszatöltése biztosítja, hogy az admin felület
     * az aktuális adatbázisállapotot kapja vissza.
     */
    public function updateScope(Scope $scope, array $attributes): Scope
    {
        $scope->fill($attributes);
        $scope->save();

        return $scope->refresh();
    }

    /**
     * Töröl egy scope-ot.
     *
     * A metódus feltételezi, hogy a hívó réteg már ellenőrizte, nem használja-e
     * aktív OAuth kliens az adott scope kódot.
     */
    public function deleteScope(Scope $scope): void
    {
        $scope->delete();
    }

    /**
     * Tömeges műveletekhez betölti a megadott scope rekordokat.
     *
     * Az admin megerősítő nézetek így nem csak az azonosítókat, hanem a tényleges
     * scope adatokat is meg tudják jeleníteni.
     *
     * @return Collection<int, Scope>
     */
    public function getByIds(array $ids): Collection
    {
        /** @var Collection<int, Scope> $scopes */
        $scopes = $this->getModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->get();

        return $scopes;
    }

    /**
     * Tömegesen törli a megadott scope-okat.
     *
     * A törölhetőségi döntést nem ez a metódus hozza meg; a service/policy rétegnek
     * kell megelőznie, hogy használatban lévő scope kerüljön eltávolításra.
     */
    public function deleteByIds(array $ids): void
    {
        $this->getModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->delete();
    }

    /**
     * Megszámolja, hogy a megadott scope kódokat hány OAuth kliens használja.
     *
     * Ez törlés vagy inaktiválás előtt fontos üzleti védelmi információ:
     * láthatóvá teszi, hogy egy scope módosítása hány kliens jogosultsági
     * konfigurációját érintené.
     *
     * @return array<string, int>
     */
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

    /**
     * Visszaadja az aktív OAuth scope kódokat.
     *
     * Ez olyan validációs és konfigurációs pontokon használható, ahol csak
     * jelenleg kiadható jogosultsági scope-ok közül lehet választani.
     *
     * @return array<int, string>
     */
    public function activeCodes(): array
    {
        /** @var array<int, string> $codes */
        $codes = $this->getModel()
            ->newQuery()
            ->where('is_active', true)
            ->orderBy('code')
            ->pluck('code')
            ->filter(static fn (mixed $code): bool => \is_string($code) && trim($code) !== '')
            ->values()
            ->all();

        return $codes;
    }
}