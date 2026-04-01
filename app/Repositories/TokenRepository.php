<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Token;
use App\Repositories\Contracts\TokenRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Prettus\Repository\Eloquent\Repository;

class TokenRepository extends Repository implements TokenRepositoryInterface
{
    /**
     * @var array<string, string>
     */
    private array $sortableFields = [
        'createdAt' => 'tokens.created_at',
        'expiresAt' => 'tokens.refresh_token_expires_at',
        'client' => 'sso_clients.name',
        'user' => 'users.name',
    ];

    public function __construct(Token $model)
    {
        parent::__construct($model);
    }

    public function model(): string
    {
        return Token::class;
    }

    public function findAccessTokenWithUserAndClientByHash(string $tokenHash): ?Token
    {
        /** @var Token|null $token */
        $token = $this->getModel()
            ->newQuery()
            ->with(['client', 'user'])
            ->where('access_token_hash', $tokenHash)
            ->first();

        return $token;
    }

    public function findAccessTokenByHash(string $tokenHash): ?Token
    {
        /** @var Token|null $token */
        $token = $this->getModel()
            ->newQuery()
            ->with('client')
            ->where('access_token_hash', $tokenHash)
            ->first();

        return $token;
    }

    public function findRefreshTokenByHash(string $tokenHash): ?Token
    {
        /** @var Token|null $token */
        $token = $this->getModel()
            ->newQuery()
            ->with('client')
            ->where('refresh_token_hash', $tokenHash)
            ->first();

        return $token;
    }

    public function findActiveAccessTokenByHash(string $tokenHash): ?Token
    {
        /** @var Token|null $token */
        $token = $this->getModel()
            ->newQuery()
            ->where('access_token_hash', $tokenHash)
            ->whereNull('access_token_revoked_at')
            ->first();

        return $token;
    }

    public function findActiveRefreshTokenByHash(string $tokenHash): ?Token
    {
        /** @var Token|null $token */
        $token = $this->getModel()
            ->newQuery()
            ->where('refresh_token_hash', $tokenHash)
            ->whereNull('refresh_token_revoked_at')
            ->first();

        return $token;
    }

    public function findTokenWithRelationsByRefreshHash(string $tokenHash): ?Token
    {
        /** @var Token|null $token */
        $token = $this->getModel()
            ->newQuery()
            ->with(['client', 'user', 'tokenPolicy', 'parentToken', 'replacedByToken'])
            ->where('refresh_token_hash', $tokenHash)
            ->first();

        return $token;
    }

    public function createTokenPair(array $attributes): Token
    {
        /** @var Token $token */
        $token = $this->getModel()->newQuery()->create($attributes);

        return $token->refresh();
    }

    public function updateToken(Token $token, array $attributes): Token
    {
        $token->forceFill($attributes)->save();

        return $token->refresh();
    }

    public function revokeAccessToken(Token $token, ?string $reason = null): void
    {
        $token->forceFill([
            'access_token_revoked_at' => now(),
            'access_token_revoked_reason' => $reason,
        ])->save();
    }

    public function revokeRefreshToken(Token $token, ?string $reason = null): void
    {
        $token->forceFill([
            'refresh_token_revoked_at' => now(),
            'refresh_token_revoked_reason' => $reason,
        ])->save();
    }

    public function markRefreshTokenRotated(Token $token, Token $replacement): Token
    {
        $token->forceFill([
            'refresh_token_used_at' => now(),
            'refresh_token_revoked_at' => now(),
            'refresh_token_revoked_reason' => 'rotated',
            'replaced_by_token_id' => $replacement->id,
        ])->save();

        return $token->refresh();
    }

    public function markRefreshReuseDetected(Token $token): Token
    {
        $token->forceFill([
            'refresh_token_reuse_detected_at' => now(),
        ])->save();

        return $token->refresh();
    }

    public function revokeTokenFamily(string $familyId, ?string $reason = null, ?int $exceptTokenId = null): void
    {
        $query = $this->getModel()
            ->newQuery()
            ->where('family_id', $familyId);

        if ($exceptTokenId !== null) {
            $query->whereKeyNot($exceptTokenId);
        }

        $query->get()->each(function (Token $token) use ($reason): void {
            $token->forceFill([
                'access_token_revoked_at' => $token->access_token_revoked_at ?? now(),
                'refresh_token_revoked_at' => $token->refresh_token_hash !== null
                    ? ($token->refresh_token_revoked_at ?? now())
                    : $token->refresh_token_revoked_at,
                'access_token_revoked_reason' => $token->access_token_revoked_reason ?? $reason,
                'refresh_token_revoked_reason' => $token->refresh_token_revoked_reason ?? $reason,
            ])->save();
        });
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
        $tokenType = $filters['token_type'] ?? 'refresh_token';
        $state = $filters['state'] ?? null;

        $column = $this->sortableFields[$sortField ?? ''] ?? $this->sortableFields['createdAt'];
        $direction = $sortOrder === -1 ? 'desc' : 'asc';

        return $this->getModel()
            ->newQuery()
            ->select('tokens.*')
            ->join('sso_clients', 'sso_clients.id', '=', 'tokens.sso_client_id')
            ->join('users', 'users.id', '=', 'tokens.user_id')
            ->with(['client', 'user', 'tokenPolicy', 'parentToken', 'replacedByToken'])
            ->when($tokenType === 'refresh_token', fn ($query) => $query->whereNotNull('tokens.refresh_token_hash'))
            ->when($global !== '', function ($query) use ($global): void {
                $query->where(function ($innerQuery) use ($global): void {
                    $innerQuery
                        ->where('sso_clients.name', 'like', "%{$global}%")
                        ->orWhere('sso_clients.client_id', 'like', "%{$global}%")
                        ->orWhere('users.name', 'like', "%{$global}%")
                        ->orWhere('users.email', 'like', "%{$global}%")
                        ->orWhere('tokens.family_id', 'like', "%{$global}%");
                });
            })
            ->when($clientId !== null, fn ($query) => $query->where('tokens.sso_client_id', (int) $clientId))
            ->when($userId !== null, fn ($query) => $query->where('tokens.user_id', (int) $userId))
            ->when($state !== null && $state !== '', function ($query) use ($state, $tokenType): void {
                $now = now();

                if ($tokenType === 'access_token') {
                    match ($state) {
                        'active' => $query->whereNull('tokens.access_token_revoked_at')->where('tokens.access_token_expires_at', '>', $now),
                        'expired' => $query->where('tokens.access_token_expires_at', '<=', $now),
                        'revoked' => $query->whereNotNull('tokens.access_token_revoked_at'),
                        default => null,
                    };

                    return;
                }

                match ($state) {
                    'active' => $query->whereNull('tokens.refresh_token_revoked_at')->whereNull('tokens.replaced_by_token_id')->where('tokens.refresh_token_expires_at', '>', $now),
                    'expired' => $query->where('tokens.refresh_token_expires_at', '<=', $now),
                    'revoked' => $query->whereNotNull('tokens.refresh_token_revoked_at')->whereNull('tokens.replaced_by_token_id'),
                    'rotated' => $query->whereNotNull('tokens.replaced_by_token_id'),
                    default => null,
                };
            })
            ->orderBy($column, $direction)
            ->paginate($perPage, ['tokens.*'], 'page', $page)
            ->withQueryString();
    }

    public function findById(int $id): ?Token
    {
        /** @var Token|null $token */
        $token = $this->getModel()
            ->newQuery()
            ->with(['client', 'user', 'tokenPolicy', 'parentToken', 'replacedByToken'])
            ->find($id);

        return $token;
    }

    public function listForClient(int $clientId): Collection
    {
        /** @var Collection<int, Token> $tokens */
        $tokens = $this->getModel()
            ->newQuery()
            ->with(['user', 'tokenPolicy'])
            ->where('sso_client_id', $clientId)
            ->latest('id')
            ->get();

        return $tokens;
    }

    public function listForUser(int $userId): Collection
    {
        /** @var Collection<int, Token> $tokens */
        $tokens = $this->getModel()
            ->newQuery()
            ->with(['client', 'tokenPolicy'])
            ->where('user_id', $userId)
            ->latest('id')
            ->get();

        return $tokens;
    }

    public function clientOptionsForAdmin(): Collection
    {
        /** @var Collection<int, array{id: int, name: string, clientId: string}> $clients */
        $clients = $this->getModel()
            ->newQuery()
            ->join('sso_clients', 'sso_clients.id', '=', 'tokens.sso_client_id')
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

    public function userOptionsForAdmin(): Collection
    {
        /** @var Collection<int, array{id: int, name: string, email: string}> $users */
        $users = $this->getModel()
            ->newQuery()
            ->join('users', 'users.id', '=', 'tokens.user_id')
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
}
