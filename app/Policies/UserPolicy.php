<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions\UserPermissions;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(UserPermissions::VIEW_ANY);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(UserPermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(UserPermissions::CREATE);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(UserPermissions::UPDATE);
    }

    public function viewSelf(User $user, User $model): bool
    {
        return $user->is($model);
    }

    public function updateSelf(User $user, User $model): bool
    {
        return $user->is($model);
    }

    public function updateOwnPassword(User $user, User $model): bool
    {
        return $user->is($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(UserPermissions::DELETE) && ! $user->is($model);
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can(UserPermissions::DELETE_ANY);
    }
}
