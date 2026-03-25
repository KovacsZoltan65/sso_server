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
     * @return array<int, array{label: string, value: string, groupKey: string, groupLabel: string, action: string, itemLabel: string, helper: string}>
     */
    public static function scopeOptions(): array
    {
        return [
            [
                'label' => 'openid',
                'value' => 'openid',
                'groupKey' => 'identity',
                'groupLabel' => 'Identity',
                'action' => 'openid',
                'itemLabel' => 'OpenID',
                'helper' => 'Authenticate the subject and issue an ID token.',
            ],
            [
                'label' => 'profile',
                'value' => 'profile',
                'groupKey' => 'identity',
                'groupLabel' => 'Identity',
                'action' => 'profile',
                'itemLabel' => 'Profile',
                'helper' => 'Access standard profile claims for the subject.',
            ],
            [
                'label' => 'email',
                'value' => 'email',
                'groupKey' => 'identity',
                'groupLabel' => 'Identity',
                'action' => 'email',
                'itemLabel' => 'Email',
                'helper' => 'Access verified email claims for the subject.',
            ],
            [
                'label' => 'offline_access',
                'value' => 'offline_access',
                'groupKey' => 'session',
                'groupLabel' => 'Session',
                'action' => 'offlineAccess',
                'itemLabel' => 'Offline Access',
                'helper' => 'Allow refresh-token based session continuation.',
            ],
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public static function tokenPolicies(): array
    {
        return [];
    }
}
