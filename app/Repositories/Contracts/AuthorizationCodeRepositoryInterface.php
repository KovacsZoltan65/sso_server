<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\AuthorizationCode;

interface AuthorizationCodeRepositoryInterface
{
    public function findWithRelationsByCodeHashForUpdate(string $codeHash): ?AuthorizationCode;

    public function consume(AuthorizationCode $authorizationCode): AuthorizationCode;
}
