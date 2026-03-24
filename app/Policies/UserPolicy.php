<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('users.view');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('users.manage') || $user->is($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('users.manage') && ! $user->is($model);
    }
}
