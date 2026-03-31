<?php

namespace App\Policies;

use App\Models\TokenPolicy;
use App\Models\User;
use App\Support\Permissions\TokenPolicyPermissions;

class TokenPolicyPolicy
{
    /**
     * Determine whether the user may list token policies.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TokenPolicyPermissions::VIEW_ANY);
    }

    /**
     * Determine whether the user may view a token policy.
     */
    public function view(User $user, TokenPolicy $tokenPolicy): bool
    {
        return $user->can(TokenPolicyPermissions::VIEW)
            || $user->can(TokenPolicyPermissions::VIEW_ANY);
    }

    /**
     * Determine whether the user may create token policies.
     */
    public function create(User $user): bool
    {
        return $user->can(TokenPolicyPermissions::CREATE);
    }

    /**
     * Determine whether the user may update a token policy.
     */
    public function update(User $user, TokenPolicy $tokenPolicy): bool
    {
        return $user->can(TokenPolicyPermissions::UPDATE);
    }

    /**
     * Determine whether the user may delete a token policy.
     */
    public function delete(User $user, TokenPolicy $tokenPolicy): bool
    {
        return $user->can(TokenPolicyPermissions::DELETE);
    }

    /**
     * Determine whether the user may bulk-delete token policies.
     */
    public function bulkDelete(User $user): bool
    {
        return $user->can(TokenPolicyPermissions::DELETE_ANY);
    }
}
