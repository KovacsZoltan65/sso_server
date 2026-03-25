<?php

namespace App\Support\Permissions;

final class TokenPolicyPermissions
{
    public const VIEW_ANY = 'token-policies.viewAny';
    public const VIEW = 'token-policies.view';
    public const CREATE = 'token-policies.create';
    public const UPDATE = 'token-policies.update';
    public const DELETE = 'token-policies.delete';
    public const DELETE_ANY = 'token-policies.deleteAny';

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
