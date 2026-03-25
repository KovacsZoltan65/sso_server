<?php

namespace App\Support\Permissions;

final class RolePermissions
{
    public const VIEW_ANY = 'roles.viewAny';
    public const VIEW = 'roles.view';
    public const CREATE = 'roles.create';
    public const UPDATE = 'roles.update';
    public const DELETE = 'roles.delete';
    public const DELETE_ANY = 'roles.deleteAny';

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