<?php

namespace App\Support;

class SsoPermissions
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function grouped(): array
    {
        return [
            'core' => [
                'dashboard.view',
                'users.view',
                'users.manage',
                'roles.view',
                'roles.manage',
                'permissions.view',
                'permissions.manage',
                'audit-logs.view',
            ],
            'sso' => [
                'sso-clients.view',
                'sso-clients.manage',
                'redirect-uris.view',
                'redirect-uris.manage',
                'scopes.view',
                'scopes.manage',
                'secrets.view',
                'secrets.manage',
                'token-policies.view',
                'token-policies.manage',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_values(array_merge(...array_values(self::grouped())));
    }
}
