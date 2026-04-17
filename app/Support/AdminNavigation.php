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
                'label' => __('navigation.dashboard.label'),
                'route' => 'dashboard',
                'icon' => 'pi pi-home',
                'permission' => null,
                'description' => __('navigation.dashboard.description'),
            ],
            [
                'key' => 'users',
                'label' => __('navigation.users.label'),
                'route' => 'admin.users.index',
                'icon' => 'pi pi-users',
                'permission' => 'users.viewAny',
                'description' => __('navigation.users.description'),
            ],
            [
                'key' => 'roles',
                'label' => __('navigation.roles.label'),
                'route' => 'admin.roles.index',
                'icon' => 'pi pi-id-card',
                'permission' => 'roles.viewAny',
                'description' => __('navigation.roles.description'),
            ],
            [
                'key' => 'permissions',
                'label' => __('navigation.permissions.label'),
                'route' => 'admin.permissions.index',
                'icon' => 'pi pi-shield',
                'permission' => 'permissions.viewAny',
                'description' => __('navigation.permissions.description'),
            ],
            [
                'key' => 'sso-clients',
                'label' => __('navigation.sso_clients.label'),
                'route' => 'admin.sso-clients.index',
                'icon' => 'pi pi-desktop',
                'permission' => 'clients.viewAny',
                'description' => __('navigation.sso_clients.description'),
            ],
            [
                'key' => 'client-user-access',
                'label' => __('navigation.client_access.label'),
                'route' => 'admin.client-user-access.index',
                'icon' => 'pi pi-link',
                'permission' => 'client-access.viewAny',
                'description' => __('navigation.client_access.description'),
            ],
            [
                'key' => 'scopes',
                'label' => __('navigation.scopes.label'),
                'route' => 'admin.scopes.index',
                'icon' => 'pi pi-sitemap',
                'permission' => 'scopes.viewAny',
                'description' => __('navigation.scopes.description'),
            ],
            [
                'key' => 'token-policies',
                'label' => __('navigation.token_policies.label'),
                'route' => 'admin.token-policies.index',
                'icon' => 'pi pi-key',
                'permission' => 'token-policies.viewAny',
                'description' => __('navigation.token_policies.description'),
            ],
            [
                'key' => 'remembered-consents',
                'label' => __('navigation.remembered_consents.label'),
                'route' => 'admin.remembered-consents.index',
                'icon' => 'pi pi-verified',
                'permission' => 'remembered-consents.viewAny',
                'description' => __('navigation.remembered_consents.description'),
            ],
            [
                'key' => 'tokens',
                'label' => __('navigation.tokens.label'),
                'route' => 'admin.tokens.index',
                'icon' => 'pi pi-ticket',
                'permission' => 'tokens.viewAny',
                'description' => __('navigation.tokens.description'),
            ],
            [
                'key' => 'audit-logs',
                'label' => __('navigation.audit_logs.label'),
                'route' => 'admin.audit-logs.index',
                'icon' => 'pi pi-history',
                'permission' => 'audit-logs.viewAny',
                'description' => __('navigation.audit_logs.description'),
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
