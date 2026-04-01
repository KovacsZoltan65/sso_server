<?php

namespace App\Support\Permissions;

final class ClientUserAccessPermissions
{
    public const VIEW_ANY = 'client-access.viewAny';
    public const VIEW = 'client-access.view';
    public const CREATE = 'client-access.create';
    public const UPDATE = 'client-access.update';
    public const DELETE = 'client-access.delete';
    public const DELETE_ANY = 'client-access.deleteAny';
    public const FORCE_DELETE = 'client-access.forceDelete';
    public const FORCE_DELETE_ANY = 'client-access.forceDeleteAny';
    public const RESTORE = 'client-access.restore';
    public const RESTORE_ANY = 'client-access.restoreAny';


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
            self::FORCE_DELETE,
            self::FORCE_DELETE_ANY,
            self::RESTORE,
            self::RESTORE_ANY,
        ];
    }
}
