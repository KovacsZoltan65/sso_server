<?php

namespace App\Policies;

use App\Models\TokenPolicy;
use App\Models\User;
use App\Support\Permissions\TokenPolicyPermissions;

class TokenPolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(TokenPolicyPermissions::VIEW_ANY) || $user->can('token-policies.view');
    }

    public function view(User $user, TokenPolicy $tokenPolicy): bool
    {
        return $user->can(TokenPolicyPermissions::VIEW)
            || $user->can(TokenPolicyPermissions::VIEW_ANY)
            || $user->can('token-policies.view');
    }

    public function create(User $user): bool
    {
        return $user->can(TokenPolicyPermissions::CREATE) || $user->can('token-policies.manage');
    }

    public function update(User $user, TokenPolicy $tokenPolicy): bool
    {
        return $user->can(TokenPolicyPermissions::UPDATE) || $user->can('token-policies.manage');
    }

    public function delete(User $user, TokenPolicy $tokenPolicy): bool
    {
        return $user->can(TokenPolicyPermissions::DELETE) || $user->can('token-policies.manage');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can(TokenPolicyPermissions::DELETE_ANY) || $user->can('token-policies.manage');
    }
}
