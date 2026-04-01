<?php

namespace App\Policies;

use App\Models\ClientUserAccess;
use App\Models\User;
use App\Support\Permissions\ClientUserAccessPermissions;

class ClientUserAccessPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(ClientUserAccessPermissions::VIEW_ANY);
    }

    public function view(User $user, ClientUserAccess $access): bool
    {
        return $user->can(ClientUserAccessPermissions::VIEW)
            || $user->can(ClientUserAccessPermissions::VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $user->can(ClientUserAccessPermissions::CREATE);
    }

    public function update(User $user, ClientUserAccess $access): bool
    {
        return $user->can(ClientUserAccessPermissions::UPDATE);
    }

    public function delete(User $user, ClientUserAccess $access): bool
    {
        return $user->can(ClientUserAccessPermissions::DELETE);
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can(ClientUserAccessPermissions::DELETE_ANY);
    }
}
