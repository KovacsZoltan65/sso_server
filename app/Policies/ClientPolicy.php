<?php

namespace App\Policies;

use App\Models\SsoClient;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clients.viewAny') || $user->can('sso-clients.view');
    }

    public function view(User $user, SsoClient $client): bool
    {
        return $user->can('clients.view') || $user->can('sso-clients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('clients.create') || $user->can('sso-clients.manage');
    }

    public function update(User $user, SsoClient $client): bool
    {
        return $user->can('clients.update') || $user->can('sso-clients.manage');
    }

    public function delete(User $user, SsoClient $client): bool
    {
        return $user->can('clients.delete') || $user->can('sso-clients.manage');
    }
}
