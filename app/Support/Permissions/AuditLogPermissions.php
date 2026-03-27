<?php

namespace App\Support\Permissions;

final class AuditLogPermissions
{
    public const VIEW_ANY = 'audit-logs.viewAny';
    public const VIEW = 'audit-logs.view';

    public static function all(): array
    {
        return [
            self::VIEW_ANY,
            self::VIEW,
        ];
    }
}
