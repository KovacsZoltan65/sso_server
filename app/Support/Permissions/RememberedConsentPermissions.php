<?php

namespace App\Support\Permissions;

final class RememberedConsentPermissions
{
    public const VIEW_ANY = 'remembered-consents.viewAny';
    public const VIEW = 'remembered-consents.view';
    public const REVOKE = 'remembered-consents.revoke';

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
