<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Token;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TokenRepositoryInterface
{
    public function findAccessTokenWithUserAndClientByHash(string $tokenHash): ?Token;

    public function findAccessTokenByHash(string $tokenHash): ?Token;

    public function findRefreshTokenByHash(string $tokenHash): ?Token;

    public function findActiveAccessTokenByHash(string $tokenHash): ?Token;

    public function findActiveRefreshTokenByHash(string $tokenHash): ?Token;

    public function findTokenWithRelationsByRefreshHash(string $tokenHash): ?Token;

    public function createTokenPair(array $attributes): Token;

    public function updateToken(Token $token, array $attributes): Token;

    public function revokeAccessToken(Token $token, ?string $reason = null): void;

    public function revokeRefreshToken(Token $token, ?string $reason = null): void;

    public function markRefreshTokenRotated(Token $token, Token $replacement): Token;

    public function markRefreshReuseDetected(Token $token): Token;

    public function revokeTokenFamily(string $familyId, ?string $reason = null, ?int $exceptTokenId = null): void;

    public function paginateForAdmin(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator;

    public function findById(int $id): ?Token;

    /**
     * @return Collection<int, Token>
     */
    public function listForClient(int $clientId): Collection;

    /**
     * @return Collection<int, Token>
     */
    public function listForUser(int $userId): Collection;

    /**
     * @return Collection<int, array{id: int, name: string, clientId: string}>
     */
    public function clientOptionsForAdmin(): Collection;

    /**
     * @return Collection<int, array{id: int, name: string, email: string}>
     */
    public function userOptionsForAdmin(): Collection;
}
