<?php

namespace App\Policies;

use App\Models\Scope;
use App\Models\User;
use App\Support\Permissions\ScopePermissions;

class ScopePolicy
{
    /**
     * Determine whether the user may list scopes.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(ScopePermissions::VIEW_ANY);
    }

    /**
     * Determine whether the user may view a scope.
     */
    public function view(User $user, Scope $scope): bool
    {
        return $user->can(ScopePermissions::VIEW);
    }

    /**
     * Determine whether the user may create scopes.
     */
    public function create(User $user): bool
    {
        return $user->can(ScopePermissions::CREATE);
    }

    /**
     * Determine whether the user may update a scope.
     */
    public function update(User $user, Scope $scope): bool
    {
        return $user->can(ScopePermissions::UPDATE);
    }

    /**
     * Determine whether the user may delete a scope.
     */
    public function delete(User $user, Scope $scope): bool
    {
        return $user->can(ScopePermissions::DELETE);
    }

    /**
     * Determine whether the user may bulk-delete scopes.
     */
    public function bulkDelete(User $user): bool
    {
        return $user->can(ScopePermissions::DELETE_ANY);
    }
}
