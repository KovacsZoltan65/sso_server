<?php

namespace App\Policies;

use App\Models\Scope;
use App\Models\User;
use App\Support\Permissions\ScopePermissions;

class ScopePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(ScopePermissions::VIEW_ANY);
    }

    public function view(User $user, Scope $scope): bool
    {
        return $user->can(ScopePermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(ScopePermissions::CREATE);
    }

    public function update(User $user, Scope $scope): bool
    {
        return $user->can(ScopePermissions::UPDATE);
    }

    public function delete(User $user, Scope $scope): bool
    {
        return $user->can(ScopePermissions::DELETE);
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can(ScopePermissions::DELETE_ANY);
    }
}
