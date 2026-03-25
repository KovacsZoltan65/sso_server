<?php

namespace App\Support\Permissions;

final class PermissionPermissions
{
    public const VIEW_ANY = 'permissions.viewAny';
    public const VIEW = 'permissions.view';
    public const CREATE = 'permissions.create';
    public const UPDATE = 'permissions.update';
    public const DELETE = 'permissions.delete';
    public const DELETE_ANY = 'permissions.deleteAny';

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