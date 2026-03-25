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
        ];
    }
}
