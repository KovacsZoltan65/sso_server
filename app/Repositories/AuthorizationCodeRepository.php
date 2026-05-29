<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuthorizationCode;
use App\Repositories\Contracts\AuthorizationCodeRepositoryInterface;
use Prettus\Repository\Eloquent\Repository;

class AuthorizationCodeRepository extends Repository implements AuthorizationCodeRepositoryInterface
{
    public function __construct(AuthorizationCode $model)
    {
        parent::__construct($model);
    }

    public function model(): string
    {
        return AuthorizationCode::class;
    }

    public function findWithRelationsByCodeHashForUpdate(string $codeHash): ?AuthorizationCode
    {
        /** @var AuthorizationCode|null $authorizationCode */
        $authorizationCode = $this->getModel()
            ->newQuery()
            ->with(['client', 'tokenPolicy', 'user'])
            ->where('code_hash', $codeHash)
            ->lockForUpdate()
            ->first();

        return $authorizationCode;
    }

    public function consume(AuthorizationCode $authorizationCode): AuthorizationCode
    {
        $authorizationCode->forceFill(['consumed_at' => now()])->save();

        return $authorizationCode->refresh();
    }
}
