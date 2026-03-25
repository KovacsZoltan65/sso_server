<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;
use App\Support\Permissions\RolePermissions;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(RolePermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(RolePermissions::CREATE) || $user->can('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can(RolePermissions::UPDATE) || $user->can('roles.manage');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can(RolePermissions::DELETE) || $user->can('roles.manage');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can(RolePermissions::DELETE_ANY) || $user->can('roles.manage');
    }
}
