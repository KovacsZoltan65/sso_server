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

    public function update(User $user, User $model): bool
    {
        return $user->can(UserPermissions::MANAGE) || $user->is($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(UserPermissions::MANAGE) && ! $user->is($model);
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can(UserPermissions::BULK_DELETE);
    }
}
