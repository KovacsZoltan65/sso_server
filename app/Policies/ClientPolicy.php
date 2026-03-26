<?php

namespace App\Policies;

use App\Models\ClientSecret;
use App\Models\SsoClient;
use App\Models\User;
use App\Support\Permissions\ClientPermissions;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sso-clients.view');
    }

    public function view(User $user, SsoClient $client): bool
    {
        return $user->can('sso-clients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sso-clients.manage');
    }

    public function update(User $user, SsoClient $client): bool
    {
        return $user->can('sso-clients.manage');
    }

    public function delete(User $user, SsoClient $client): bool
    {
        return $user->can('sso-clients.manage');
    }

    public function manageSecrets(User $user, SsoClient $client): bool
    {
        return $user->can('clients.manageSecrets');
    }

    public function rotateSecret(User $user, SsoClient $client): bool
    {
        return $user->can('clients.rotateSecret')
            || $user->can('clients.manageSecrets');
    }

    public function revokeSecret(User $user, SsoClient $client, ClientSecret $secret): bool
    {
        return $user->can('clients.manageSecrets')
            && (int) $secret->sso_client_id === (int) $client->id;
    }
}
