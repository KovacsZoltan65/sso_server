<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use App\Support\Permissions\PermissionPermissions;

class PermissionPolicy
{
    /**
     * Determine whether the user may list permissions.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionPermissions::VIEW_ANY);
    }

    /**
     * Determine whether the user may view a permission.
     */
    public function view(User $user, Permission $permission): bool
    {
        return $user->can(PermissionPermissions::VIEW);
    }

    /**
     * Determine whether the user may create permissions.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionPermissions::CREATE);
    }

    /**
     * Determine whether the user may update a permission.
     */
    public function update(User $user, Permission $permission): bool
    {
        return $user->can(PermissionPermissions::UPDATE);
    }

    /**
     * Determine whether the user may delete a permission.
     */
    public function delete(User $user, Permission $permission): bool
    {
        return $user->can(PermissionPermissions::DELETE);
    }

    /**
     * Determine whether the user may bulk-delete permissions.
     */
    public function bulkDelete(User $user): bool
    {
        return $user->can(PermissionPermissions::DELETE_ANY);
    }
}
