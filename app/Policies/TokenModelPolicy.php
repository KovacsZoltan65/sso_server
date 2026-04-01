<?php

namespace App\Policies;

use App\Models\Token;
use App\Models\User;
use App\Support\Permissions\TokenPermissions;

class TokenModelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(TokenPermissions::VIEW_ANY);
    }

    public function view(User $user, Token $token): bool
    {
        return $user->can(TokenPermissions::VIEW)
            || $user->can(TokenPermissions::VIEW_ANY);
    }

    public function revoke(User $user, Token $token): bool
    {
        return $user->can(TokenPermissions::REVOKE);
    }

    public function revokeFamily(User $user): bool
    {
        return $user->can(TokenPermissions::REVOKE_FAMILY);
    }
}
