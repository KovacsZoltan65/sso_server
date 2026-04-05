<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UserClientConsent;
use App\Repositories\Contracts\UserClientConsentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Prettus\Repository\Eloquent\Repository;

class UserClientConsentRepository extends Repository implements UserClientConsentRepositoryInterface
{
    /**
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

    public function model(): string
    {
        return UserClientConsent::class;
    }

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
                    'active' => $query->whereNull('user_client_consents.revoked_at')->where('user_client_consents.expires_at', '>', now()),
                    'revoked' => $query->whereNotNull('user_client_consents.revoked_at'),
                    'expired' => $query->whereNull('user_client_consents.revoked_at')->where('user_client_consents.expires_at', '<=', now()),
                    default => null,
                };
            })
            ->orderBy($column, $direction)
            ->paginate($perPage, ['user_client_consents.*'], 'page', $page)
            ->withQueryString();
    }

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

    public function updateConsent(UserClientConsent $consent, array $attributes): UserClientConsent
    {
        $consent->forceFill($attributes)->save();

        return $consent->refresh();
    }
}
