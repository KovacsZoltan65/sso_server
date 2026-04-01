<?php

namespace App\Support;

class SsoPermissions
{
    /**
     * @return array<int, string>
     */
    public static function coreResources(): array
    {
        return [
            'users',
            'roles',
            'permissions',
            'audit-logs',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function ssoResources(): array
    {
        return [
            'clients',
            'client-access',
            'redirect-uris',
            'scopes',
            'secrets',
            'token-policies',
            'tokens',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function resources(): array
    {
        return [
            'users',
            'roles',
            'permissions',
            'clients',
            'client-access',
            'redirect-uris',
            'scopes',
            'secrets',
            'token-policies',
            'audit-logs',
            'tokens',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function baseActions(): array
    {
        return [
            'viewAny',
            'view',
            'create',
            'update',
            'delete',
            'deleteAny',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function destructiveActions(): array
    {
        return [
            'restore',
            'restoreAny',
            'forceDelete',
            'forceDeleteAny',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function extraPermissions(): array
    {
        return [
            'users.assignRole',
            'users.revokeRole',
            'roles.assignPermission',
            'roles.revokePermission',
            'tokens.issueToken',
            'tokens.revokeToken',
            'tokens.refreshToken',
            'clients.manageSecrets',
            'clients.rotateSecret',
            'clients.revokeSecret',
            'dashboard.view',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function legacyPermissions(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public static function permissionsForResources(array $resources, array $actions): array
    {
        $permissions = [];

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                $permissions[] = "{$resource}.{$action}";
            }
        }

        return $permissions;
    }

    /**
     * @return array<int, string>
     */
    public static function standardizedPermissions(): array
    {
        return array_values(array_unique(array_merge(
            self::permissionsForResources(self::resources(), self::baseActions()),
            self::permissionsForResources(self::resources(), self::destructiveActions()),
            self::extraPermissions(),
        )));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function grouped(): array
    {
        return [
            'core' => array_values(array_unique(array_merge(
                self::permissionsForResources(self::coreResources(), self::baseActions()),
                self::permissionsForResources(self::coreResources(), self::destructiveActions()),
                [
                    'users.assignRole',
                    'users.revokeRole',
                    'roles.assignPermission',
                    'roles.revokePermission',
                    'dashboard.view',
                ],
            ))),
            'sso' => array_values(array_unique(array_merge(
                self::permissionsForResources(self::ssoResources(), self::baseActions()),
                self::permissionsForResources(self::ssoResources(), self::destructiveActions()),
                [
                    'clients.manageSecrets',
                    'clients.rotateSecret',
                    'clients.revokeSecret',
                    'tokens.issueToken',
                    'tokens.revokeToken',
                    'tokens.refreshToken',
                ],
            ))),
            'standardized' => self::standardizedPermissions(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return self::standardizedPermissions();
    }

    /**
     * @return array<int, string>
     */
    public static function adminPermissions(): array
    {
        return array_values(array_unique(array_merge(
            [
                'dashboard.view',
            ],
            self::permissionsForResources([
                'users',
                'roles',
                'permissions',
                'clients',
                'client-access',
                'redirect-uris',
                'scopes',
                'secrets',
                'token-policies',
                'audit-logs',
                'tokens',
            ], self::baseActions()),
            [
                'users.assignRole',
                'users.revokeRole',
                'roles.assignPermission',
                'roles.revokePermission',
                'clients.manageSecrets',
                'clients.rotateSecret',
                'clients.revokeSecret',
                'tokens.issueToken',
                'tokens.revokeToken',
                'tokens.refreshToken',
            ],
        )));
    }
}
