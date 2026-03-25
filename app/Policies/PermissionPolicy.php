<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use App\Support\Permissions\PermissionPermissions;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionPermissions::VIEW_ANY);
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->can(PermissionPermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionPermissions::CREATE);
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->can(PermissionPermissions::UPDATE);
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->can(PermissionPermissions::DELETE);
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can(PermissionPermissions::DELETE_ANY);
    }
}
