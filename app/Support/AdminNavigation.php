<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class AdminNavigation
{
    /**
     * @return array<int, array<string, string|null>>
     */
    public static function items(): array
    {
        return [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'route' => 'dashboard',
                'icon' => 'pi pi-home',
                'permission' => null,
                'description' => 'Platform health, activity, and bootstrap status.',
            ],
            [
                'key' => 'users',
                'label' => 'Users',
                'route' => 'admin.users.index',
                'icon' => 'pi pi-users',
                'permission' => 'users.view',
                'description' => 'Operator directory and read-model example backed by the repository layer.',
            ],
            [
                'key' => 'roles',
                'label' => 'Roles',
                'route' => 'admin.roles.index',
                'icon' => 'pi pi-id-card',
                'permission' => 'roles.view',
                'description' => 'Role definitions and future access bundles.',
            ],
            [
                'key' => 'permissions',
                'label' => 'Permissions',
                'route' => 'admin.permissions.index',
                'icon' => 'pi pi-shield',
                'permission' => 'permissions.view',
                'description' => 'Granular capability matrix for admin and SSO modules.',
            ],
            [
                'key' => 'sso-clients',
                'label' => 'SSO Clients',
                'route' => 'admin.sso-clients.index',
                'icon' => 'pi pi-desktop',
                'permission' => 'sso-clients.view',
                'description' => 'Client registrations, redirect targets, secrets, and token rules.',
            ],
            [
                'key' => 'scopes',
                'label' => 'Scopes',
                'route' => 'admin.scopes.index',
                'icon' => 'pi pi-sitemap',
                'permission' => 'scopes.viewAny',
                'description' => 'Scope catalog and consent policy definitions.',
            ],
            [
                'key' => 'token-policies',
                'label' => 'Token Policies',
                'route' => 'admin.token-policies.index',
                'icon' => 'pi pi-key',
                'permission' => 'token-policies.viewAny',
                'description' => 'TTL, rotation, signing, and revocation policy management.',
            ],
            [
                'key' => 'audit-logs',
                'label' => 'Audit Logs',
                'route' => 'admin.audit-logs.index',
                'icon' => 'pi pi-history',
                'permission' => 'audit-logs.view',
                'description' => 'Administrative actions and future security event review.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public static function forUser(?Authenticatable $user): array
    {
        return Collection::make(self::items())
            ->filter(fn (array $item) => $item['permission'] === null || ($user && $user->can($item['permission'])))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string|null>
     */
    public static function find(string $key): array
    {
        return Collection::make(self::items())
            ->firstOrFail(fn (array $item) => $item['key'] === $key);
    }
}
