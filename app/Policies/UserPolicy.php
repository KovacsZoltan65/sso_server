<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions\UserPermissions;

class UserPolicy
{
    /**
     * Determine whether the user may list users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(UserPermissions::VIEW_ANY);
    }

    /**
     * Determine whether the user may view a specific user record.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can(UserPermissions::VIEW);
    }

    /**
     * Determine whether the user may create a user.
     */
    public function create(User $user): bool
    {
        return $user->can(UserPermissions::CREATE);
    }

    /**
     * Determine whether the user may update a specific user.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can(UserPermissions::UPDATE);
    }

    /**
     * Determine whether the user may view their own self-service profile.
     */
    public function viewSelf(User $user, User $model): bool
    {
        return $user->is($model);
    }

    /**
     * Determine whether the user may update their own self-service profile.
     */
    public function updateSelf(User $user, User $model): bool
    {
        return $user->is($model);
    }

    /**
     * Determine whether the user may update their own password.
     */
    public function updateOwnPassword(User $user, User $model): bool
    {
        return $user->is($model);
    }

    /**
     * Determine whether the user may delete another user.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->can(UserPermissions::DELETE) && ! $user->is($model);
    }

    /**
     * Determine whether the user may perform bulk user deletion.
     */
    public function bulkDelete(User $user): bool
    {
        return $user->can(UserPermissions::DELETE_ANY);
    }
}
