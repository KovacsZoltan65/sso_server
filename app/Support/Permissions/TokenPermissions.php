<?php

namespace App\Support\Permissions;

final class TokenPermissions
{
    public const VIEW_ANY = 'tokens.viewAny';
    public const VIEW = 'tokens.view';
    public const REVOKE = 'tokens.revokeToken';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::VIEW_ANY,
            self::VIEW,
            self::REVOKE,
        ];
    }
}
