<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use App\Support\Permissions\PermissionPermissions;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionPermissions::VIEW_ANY) || $user->can('permissions.view');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->can(PermissionPermissions::VIEW) || $user->can('permissions.view');
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionPermissions::CREATE) || $user->can('permissions.manage');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->can(PermissionPermissions::UPDATE) || $user->can('permissions.manage');
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->can(PermissionPermissions::DELETE) || $user->can('permissions.manage');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can(PermissionPermissions::DELETE_ANY) || $user->can('permissions.manage');
    }
}
