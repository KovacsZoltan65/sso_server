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
        return $user->can(ClientPermissions::VIEW_ANY);
    }

    public function view(User $user, SsoClient $client): bool
    {
        return $user->can(ClientPermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(ClientPermissions::CREATE);
    }

    public function update(User $user, SsoClient $client): bool
    {
        return $user->can(ClientPermissions::UPDATE);
    }

    public function delete(User $user, SsoClient $client): bool
    {
        return $user->can(ClientPermissions::DELETE);
    }

    public function manageSecrets(User $user, SsoClient $client): bool
    {
        return $user->can(ClientPermissions::MANAGE_SECRETS);
    }

    public function rotateSecret(User $user, SsoClient $client): bool
    {
        return $user->can(ClientPermissions::ROTATE_SECRET)
            || $user->can(ClientPermissions::MANAGE_SECRETS);
    }

    public function revokeSecret(User $user, SsoClient $client, ClientSecret $secret): bool
    {
        return ($user->can(ClientPermissions::REVOKE_SECRET)
            || $user->can(ClientPermissions::MANAGE_SECRETS))
            && (int) $secret->sso_client_id === (int) $client->id;
    }
}
