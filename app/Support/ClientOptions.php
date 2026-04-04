<?php

namespace App\Support;

use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use Illuminate\Support\Facades\Schema;

class ClientOptions
{
    /**
     * @return array<int, array{name: string, code: string, description: string, is_active: bool}>
     */
    public static function defaultScopeDefinitions(): array
    {
        return [
            [
                'name' => 'OpenID',
                'code' => 'openid',
                'description' => 'Authenticate the subject and issue an ID token.',
                'is_active' => true,
            ],
            [
                'name' => 'Profile',
                'code' => 'profile',
                'description' => 'Access standard profile claims for the subject.',
                'is_active' => true,
            ],
            [
                'name' => 'Email',
                'code' => 'email',
                'description' => 'Access verified email claims for the subject.',
                'is_active' => true,
            ],
            [
                'name' => 'Offline Access',
                'code' => 'offline_access',
                'description' => 'Allow refresh-token based session continuation.',
                'is_active' => true,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function scopeValues(): array
    {
        return array_map(
            fn (array $scope) => $scope['code'],
            self::availableScopes(),
        );
    }

    /**
     * @return array<int, array{label: string, value: string, groupKey: string, groupLabel: string, action: string, itemLabel: string, helper: string}>
     */
    public static function scopeOptions(): array
    {
        return array_map(function (array $scope): array {
            $groupKey = str_contains($scope['code'], '.')
                ? explode('.', $scope['code'])[0]
                : 'identity';
            $groupLabel = str_contains($scope['code'], '.')
                ? str($groupKey)->replace('-', ' ')->title()->value()
                : (in_array($scope['code'], ['openid', 'profile', 'email'], true) ? 'Identity' : 'Session');
            $action = str_contains($scope['code'], '.')
                ? explode('.', $scope['code'], 2)[1]
                : $scope['code'];

            return [
                'label' => $scope['code'],
                'value' => $scope['code'],
                'groupKey' => $groupKey,
                'groupLabel' => $groupLabel,
                'action' => $action,
                'itemLabel' => $scope['name'],
                'helper' => $scope['description'],
            ];
        }, self::availableScopes());
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public static function tokenPolicies(): array
    {
        if (! Schema::hasTable('token_policies')) {
            return [];
        }

        return TokenPolicy::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (TokenPolicy $tokenPolicy) => [
                'id' => $tokenPolicy->id,
                'name' => $tokenPolicy->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: string, helper: string}>
     */
    public static function trustTierOptions(): array
    {
        return [
            [
                'label' => 'First-party trusted',
                'value' => SsoClient::TRUST_TIER_FIRST_PARTY_TRUSTED,
                'helper' => 'Internal client with the highest planned trust baseline.',
            ],
            [
                'label' => 'First-party untrusted',
                'value' => SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED,
                'helper' => 'Internal client that still requires explicit consent behavior.',
            ],
            [
                'label' => 'Third-party',
                'value' => SsoClient::TRUST_TIER_THIRD_PARTY,
                'helper' => 'Default partner or external application trust baseline.',
            ],
            [
                'label' => 'Machine-to-machine',
                'value' => SsoClient::TRUST_TIER_MACHINE_TO_MACHINE,
                'helper' => 'Non-interactive integration trust classification.',
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, code: string, description: string, is_active: bool}>
     */
    private static function availableScopes(): array
    {
        if (! Schema::hasTable('scopes')) {
            return self::defaultScopeDefinitions();
        }

        $scopes = Scope::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['name', 'code', 'description', 'is_active'])
            ->map(fn (Scope $scope) => [
                'name' => $scope->name,
                'code' => $scope->code,
                'description' => $scope->description ?? '',
                'is_active' => (bool) $scope->is_active,
            ])
            ->values()
            ->all();

        return $scopes !== [] ? $scopes : self::defaultScopeDefinitions();
    }
}
