<?php

namespace App\Support\OAuth;

final class RememberedConsentRevocationReasons
{
    public const ADMIN_MANUAL_REVOKE = 'admin_manual_revoke';
    public const SECURITY_INCIDENT = 'security_incident';
    public const CLIENT_ACCESS_REMOVED = 'client_access_removed';
    public const USER_REQUESTED_REVOKE = 'user_requested_revoke';
    public const TRUST_TIER_CHANGED = 'trust_tier_changed';
    public const CONSENT_BYPASS_POLICY_CHANGED = 'consent_bypass_policy_changed';
    public const CONSENT_POLICY_VERSION_CHANGED = 'consent_policy_version_changed';

    /**
     * @return array<int, string>
     */
    public static function adminSelectable(): array
    {
        return [
            self::ADMIN_MANUAL_REVOKE,
            self::SECURITY_INCIDENT,
            self::CLIENT_ACCESS_REMOVED,
            self::USER_REQUESTED_REVOKE,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function automatic(): array
    {
        return [
            self::TRUST_TIER_CHANGED,
            self::CONSENT_BYPASS_POLICY_CHANGED,
            self::CONSENT_POLICY_VERSION_CHANGED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            ...self::adminSelectable(),
            ...self::automatic(),
        ];
    }
}
