<?php

namespace App\Support\Permissions;

final class ClientPermissions
{
    public const VIEW_ANY = 'clients.viewAny';
    public const VIEW = 'clients.view';
    public const CREATE = 'clients.create';
    public const UPDATE = 'clients.update';
    public const DELETE = 'clients.delete';
    public const DELETE_ANY = 'clients.deleteAny';
    public const MANAGE_SECRETS = 'clients.manageSecrets';
    public const ROTATE_SECRET = 'clients.rotateSecret';
    public const REVOKE_SECRET = 'clients.revokeSecret';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::VIEW_ANY,
            self::VIEW,
            self::CREATE,
            self::UPDATE,
            self::DELETE,
            self::DELETE_ANY,
            self::MANAGE_SECRETS,
            self::ROTATE_SECRET,
            self::REVOKE_SECRET,
        ];
    }
}
