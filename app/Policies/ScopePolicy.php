<?php

namespace App\Policies;

use App\Models\Scope;
use App\Models\User;
use App\Support\Permissions\ScopePermissions;

class ScopePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(ScopePermissions::VIEW_ANY) || $user->can('scopes.view');
    }

    public function view(User $user, Scope $scope): bool
    {
        return $user->can('scopes.view') || $user->can(ScopePermissions::VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $user->can(ScopePermissions::CREATE) || $user->can('scopes.manage');
    }

    public function update(User $user, Scope $scope): bool
    {
        return $user->can(ScopePermissions::UPDATE) || $user->can('scopes.manage');
    }

    public function delete(User $user, Scope $scope): bool
    {
        return $user->can(ScopePermissions::DELETE) || $user->can('scopes.manage');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can(ScopePermissions::DELETE_ANY) || $user->can('scopes.manage');
    }
}
