<?php

namespace App\Policies;

use App\Models\SsoClient;
use App\Models\User;
use App\Support\Permissions\ClientPermissions;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(ClientPermissions::VIEW_ANY) || $user->can('sso-clients.view');
    }

    public function view(User $user, SsoClient $client): bool
    {
        return $user->can(ClientPermissions::VIEW) || $user->can('sso-clients.view');
    }

    public function create(User $user): bool
    {
        return $user->can(ClientPermissions::CREATE) || $user->can('sso-clients.manage');
    }

    public function update(User $user, SsoClient $client): bool
    {
        return $user->can(ClientPermissions::UPDATE) || $user->can('sso-clients.manage');
    }

    public function delete(User $user, SsoClient $client): bool
    {
        return $user->can(ClientPermissions::DELETE) || $user->can('sso-clients.manage');
    }
}
