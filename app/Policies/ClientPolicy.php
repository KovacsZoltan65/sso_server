<?php

namespace App\Policies;

use App\Models\ClientSecret;
use App\Models\SsoClient;
use App\Models\User;
use App\Support\Permissions\ClientPermissions;

class ClientPolicy
{
    /**
     * Determine whether the user may list registered SSO clients.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(ClientPermissions::VIEW_ANY);
    }

    /**
     * Determine whether the user may view a specific SSO client.
     */
    public function view(User $user, SsoClient $client): bool
    {
        return $user->can(ClientPermissions::VIEW);
    }

    /**
     * Determine whether the user may create a new SSO client.
     */
    public function create(User $user): bool
    {
        return $user->can(ClientPermissions::CREATE);
    }

    /**
     * Determine whether the user may update a specific SSO client.
     */
    public function update(User $user, SsoClient $client): bool
    {
        return $user->can(ClientPermissions::UPDATE);
    }

    /**
     * Determine whether the user may delete a specific SSO client.
     */
    public function delete(User $user, SsoClient $client): bool
    {
        return $user->can(ClientPermissions::DELETE);
    }

    /**
     * Determine whether the user may access client secret management actions.
     */
    public function manageSecrets(User $user, SsoClient $client): bool
    {
        return $user->can(ClientPermissions::MANAGE_SECRETS);
    }

    /**
     * Determine whether the user may rotate a client secret.
     */
    public function rotateSecret(User $user, SsoClient $client): bool
    {
        return $user->can(ClientPermissions::ROTATE_SECRET)
            || $user->can(ClientPermissions::MANAGE_SECRETS);
    }

    /**
     * Determine whether the user may revoke the given secret for the given client.
     */
    public function revokeSecret(User $user, SsoClient $client, ClientSecret $secret): bool
    {
        return ($user->can(ClientPermissions::REVOKE_SECRET)
            || $user->can(ClientPermissions::MANAGE_SECRETS))
            && (int) $secret->sso_client_id === (int) $client->id;
    }
}
