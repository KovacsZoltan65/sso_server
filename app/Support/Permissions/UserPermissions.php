<?php

namespace App\Support\Permissions;

final class UserPermissions
{
    public const VIEW_ANY = 'users.viewAny';
    public const VIEW = 'users.view';
    public const MANAGE = 'users.manage';
    public const DELETE = 'users.delete';
    public const BULK_DELETE = 'users.bulkDelete';

    public static function all(): array
    {
        return [
            self::VIEW_ANY,
            self::VIEW,
            self::MANAGE,
            self::DELETE,
            self::BULK_DELETE,
        ];
    }
}