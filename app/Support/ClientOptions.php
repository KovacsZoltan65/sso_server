<?php

namespace App\Support;

class ClientOptions
{
    /**
     * @return array<int, string>
     */
    public static function scopeValues(): array
    {
        return [
            'openid',
            'profile',
            'email',
            'offline_access',
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public static function scopeOptions(): array
    {
        return array_map(
            fn (string $scope) => [
                'label' => $scope,
                'value' => $scope,
            ],
            self::scopeValues(),
        );
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public static function tokenPolicies(): array
    {
        return [];
    }
}
