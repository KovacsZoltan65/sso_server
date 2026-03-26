<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Token;

interface TokenRepositoryInterface
{
    public function findAccessTokenWithUserAndClientByHash(string $tokenHash): ?Token;

    public function findAccessTokenByHash(string $tokenHash): ?Token;

    public function findRefreshTokenByHash(string $tokenHash): ?Token;

    public function findActiveAccessTokenByHash(string $tokenHash): ?Token;

    public function findActiveRefreshTokenByHash(string $tokenHash): ?Token;

    public function revokeAccessToken(Token $token): void;

    public function revokeRefreshToken(Token $token): void;
}
