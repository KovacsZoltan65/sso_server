<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;
use App\Support\Permissions\RolePermissions;

class RolePolicy
{
    /**
     * Determine whether the user may list roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(RolePermissions::VIEW_ANY);
    }

    /**
     * Determine whether the user may view a role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can(RolePermissions::VIEW);
    }

    /**
     * Determine whether the user may create roles.
     */
    public function create(User $user): bool
    {
        return $user->can(RolePermissions::CREATE);
    }

    /**
     * Determine whether the user may update a role.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can(RolePermissions::UPDATE);
    }

    /**
     * Determine whether the user may delete a role.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->can(RolePermissions::DELETE);
    }

    /**
     * Determine whether the user may bulk-delete roles.
     */
    public function bulkDelete(User $user): bool
    {
        return $user->can(RolePermissions::DELETE_ANY);
    }
}
