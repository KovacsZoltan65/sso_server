<?php

namespace App\Services\OAuth;

use App\Models\User;

class OidcSubjectService
{
    public function forUser(User $user): string
    {
        return (string) $user->getKey();
    }

    public function forUserId(int|string $userId): string
    {
        return (string) $userId;
    }
}
