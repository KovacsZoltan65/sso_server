<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('permissions.view');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->can('permissions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('permissions.manage');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->can('permissions.manage');
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->can('permissions.manage');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can('permissions.manage');
    }
}
