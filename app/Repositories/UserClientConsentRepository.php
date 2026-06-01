<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UserClientConsent;
use App\Repositories\Contracts\UserClientConsentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Prettus\Repository\Eloquent\Repository;

/**
 * Felhasználói OAuth consent rekordok adatkezeléséért felelős repository.
 *
 * A consent rekordok azt dokumentálják, hogy egy felhasználó mikor,
 * mely kliens számára, milyen feltételek mellett adott hozzáférést.
 * A repository adminisztrációs listázási, szűrési és karbantartási
 * műveleteket biztosít ezekhez az adatokhoz.
 */
class UserClientConsentRepository extends Repository implements UserClientConsentRepositoryInterface
{
    /**
     * Az admin lista által támogatott rendezési mezők explicit leképezése.
     *
     * A frontendből érkező oszlopnevek nem kerülnek közvetlenül SQL-be,
     * így a rendezés kontrollált és biztonságos marad.
     *
     * @var array<string, string>
     */
    private array $sortableFields = [
        'grantedAt' => 'user_client_consents.granted_at',
        'expiresAt' => 'user_client_consents.expires_at',
        'client' => 'sso_clients.name',
        'user' => 'users.name',
    ];

    public function __construct(UserClientConsent $model)
    {
        parent::__construct($model);
    }

    /**
     * Meghatározza a repository által kezelt modellt.
     *
     * A Prettus Repository infrastruktúra ezt használja az alap
     * modellfeloldáshoz és a generikus repository működéshez.
     */
    public function model(): string
    {
        return UserClientConsent::class;
    }

    /**
     * Visszaadja egy kliens jelenleg érvényes consentjeit.
     *
     * Az eredmény csak olyan jóváhagyásokat tartalmaz, amelyek nem kerültek
     * visszavonásra és még nem jártak le. Ez tipikusan audit, riport vagy
     * consent újraellenőrzési folyamatok alapja lehet.
     *
     * @return Collection<int, UserClientConsent>
     */
    public function activeForClient(int $clientId): Collection
    {
        /** @var Collection<int, UserClientConsent> $consents */
        $consents = $this->getModel()
            ->newQuery()
            ->with(['user', 'client'])
            ->where('client_id', $clientId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('granted_at')
            ->get();

        return $consents;
    }

    /**
     * Visszaadja azokat az aktív consent rekordokat, amelyek eltérő
     * consent policy verzióval rendelkeznek.
     *
     * Ez lehetővé teszi tömeges újrahozzájárulás (re-consent) folyamatok
     * indítását, amikor a rendszer jogi vagy adatkezelési feltételei
     * megváltoznak.
     *
     * @return Collection<int, UserClientConsent>
     */
    public function activeForPolicyVersionMismatch(string $currentVersion, ?string $oldVersion = null): Collection
    {
        /** @var Collection<int, UserClientConsent> $consents */
        $consents = $this->getModel()
            ->newQuery()
            ->with(['user', 'client'])
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->when(
                $oldVersion !== null && trim($oldVersion) !== '',
                fn ($query) => $query->where('consent_policy_version', trim($oldVersion)),
                fn ($query) => $query->where('consent_policy_version', '!=', trim($currentVersion)),
            )
            ->latest('granted_at')
            ->get();

        return $consents;
    }

    /**
     * Lekérdezi az admin consent listát kereséssel, szűréssel és rendezéssel.
     *
     * A lista támogatja a felhasználó, kliens és consent állapot szerinti
     * szűrést, így egyszerre használható auditálási, ügyfélszolgálati és
     * compliance célokra.
     */
    public function paginateForAdmin(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator {
        $global = trim((string) ($filters['global'] ?? ''));
        $clientId = $filters['client_id'] ?? null;
        $userId = $filters['user_id'] ?? null;
        $status = $filters['status'] ?? null;

        $column = $this->sortableFields[$sortField ?? ''] ?? $this->sortableFields['grantedAt'];
        $direction = $sortOrder === 1 ? 'asc' : 'desc';

        return $this->getModel()
            ->newQuery()
            ->select('user_client_consents.*')
            ->join('users', 'users.id', '=', 'user_client_consents.user_id')
            ->join('sso_clients', 'sso_clients.id', '=', 'user_client_consents.client_id')
            ->with(['user', 'client'])
            ->when($global !== '', function ($query) use ($global): void {
                $query->where(function ($innerQuery) use ($global): void {
                    $innerQuery
                        ->where('users.name', 'like', "%{$global}%")
                        ->orWhere('users.email', 'like', "%{$global}%")
                        ->orWhere('sso_clients.name', 'like', "%{$global}%")
                        ->orWhere('sso_clients.client_id', 'like', "%{$global}%")
                        ->orWhere('user_client_consents.revocation_reason', 'like', "%{$global}%");
                });
            })
            ->when($clientId !== null, fn ($query) => $query->where('user_client_consents.client_id', (int) $clientId))
            ->when($userId !== null, fn ($query) => $query->where('user_client_consents.user_id', (int) $userId))
            ->when($status !== null && $status !== '', function ($query) use ($status): void {
                match ($status) {
                    'active' => $query->whereNull('user_client_consents.revoked_at')
                        ->where('user_client_consents.expires_at', '>', now()),

                    'revoked' => $query->whereNotNull('user_client_consents.revoked_at'),

                    'expired' => $query->whereNull('user_client_consents.revoked_at')
                        ->where('user_client_consents.expires_at', '<=', now()),

                    default => null,
                };
            })
            ->orderBy($column, $direction)
            ->paginate($perPage, ['user_client_consents.*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * Visszaadja azokat a felhasználókat, akikhez legalább egy consent tartozik.
     *
     * Az admin szűrőlisták számára optimalizált adatforrás, hogy a kezelőfelület
     * csak ténylegesen érintett felhasználókat ajánljon fel.
     *
     * @return Collection<int, array{id: int, name: string, email: string}>
     */
    public function userOptionsForAdmin(): Collection
    {
        /** @var Collection<int, array{id: int, name: string, email: string}> $users */
        $users = $this->getModel()
            ->newQuery()
            ->join('users', 'users.id', '=', 'user_client_consents.user_id')
            ->selectRaw('distinct users.id as id, users.name as name, users.email as email')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'email' => (string) $row->email,
            ])
            ->values();

        return $users;
    }

    /**
     * Visszaadja azokat az OAuth klienseket, amelyekhez consent rekord tartozik.
     *
     * Az admin szűrők és riportok számára biztosít adatforrást úgy,
     * hogy csak ténylegesen használt kliensek jelenjenek meg.
     *
     * @return Collection<int, array{id: int, name: string, clientId: string}>
     */
    public function clientOptionsForAdmin(): Collection
    {
        /** @var Collection<int, array{id: int, name: string, clientId: string}> $clients */
        $clients = $this->getModel()
            ->newQuery()
            ->join('sso_clients', 'sso_clients.id', '=', 'user_client_consents.client_id')
            ->selectRaw('distinct sso_clients.id as id, sso_clients.name as name, sso_clients.client_id as clientId')
            ->orderBy('sso_clients.name')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'clientId' => (string) $row->clientId,
            ])
            ->values();

        return $clients;
    }

    /**
     * Frissíti egy consent rekord állapotát vagy metaadatait.
     *
     * Tipikusan visszavonási folyamatok, lejárati módosítások vagy
     * adminisztratív helyesbítések során használható.
     */
    public function updateConsent(UserClientConsent $consent, array $attributes): UserClientConsent
    {
        $consent->forceFill($attributes)->save();

        return $consent->refresh();
    }
}