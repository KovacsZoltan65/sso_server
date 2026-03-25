<?php

namespace App\Support\Permissions;

final class ScopePermissions
{
    public const VIEW_ANY = 'scopes.viewAny';
    public const VIEW = 'scopes.view';
    public const CREATE = 'scopes.create';
    public const UPDATE = 'scopes.update';
    public const DELETE = 'scopes.delete';
    public const DELETE_ANY = 'scopes.deleteAny';

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