<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Token;
use App\Repositories\Contracts\TokenRepositoryInterface;
use Prettus\Repository\Eloquent\Repository;

class TokenRepository extends Repository implements TokenRepositoryInterface
{
    public function __construct(Token $model)
    {
        parent::__construct($model);
    }

    public function model(): string
    {
        return Token::class;
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

    public function revokeAccessToken(Token $token): void
    {
        $token->forceFill([
            'access_token_revoked_at' => now(),
        ])->save();
    }

    public function revokeRefreshToken(Token $token): void
    {
        $token->forceFill([
            'refresh_token_revoked_at' => now(),
        ])->save();
    }
}