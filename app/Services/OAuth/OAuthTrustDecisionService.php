<?php

namespace App\Services\OAuth;

use App\Data\OAuth\OAuthTrustDecisionResult;
use App\Enums\OAuth\OAuthTrustDecision;
use App\Models\SsoClient;

class OAuthTrustDecisionService
{
    /**
     * First iteration trust policy:
     * skip_consent means trusted-client bypass only, not remembered consent reuse.
     *
     * @param array<int, string> $requestedScopes
     */
    public function decideForAuthorization(
        SsoClient $client,
        array $requestedScopes,
        string $responseType,
    ): OAuthTrustDecisionResult {
        $trustTier = trim((string) $client->trust_tier);
        $consentBypassAllowed = (bool) $client->consent_bypass_allowed;
        $isInteractiveAuthorization = $responseType === 'code';

        if ($trustTier === SsoClient::TRUST_TIER_MACHINE_TO_MACHINE && $isInteractiveAuthorization) {
            return new OAuthTrustDecisionResult(
                decision: OAuthTrustDecision::DenyAuthorization,
                reason: 'interactive_authorization_not_allowed',
                trustTier: $trustTier,
                consentBypassAllowed: $consentBypassAllowed,
            );
        }

        if (! in_array($trustTier, SsoClient::supportedTrustTiers(), true)) {
            return new OAuthTrustDecisionResult(
                decision: OAuthTrustDecision::ShowConsent,
                reason: 'unknown_trust_tier',
                trustTier: $trustTier !== '' ? $trustTier : null,
                consentBypassAllowed: $consentBypassAllowed,
            );
        }

        if ($trustTier === SsoClient::TRUST_TIER_THIRD_PARTY) {
            return new OAuthTrustDecisionResult(
                decision: OAuthTrustDecision::ShowConsent,
                reason: 'third_party_requires_consent',
                trustTier: $trustTier,
                consentBypassAllowed: $consentBypassAllowed,
            );
        }

        if ($trustTier === SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED) {
            return new OAuthTrustDecisionResult(
                decision: OAuthTrustDecision::ShowConsent,
                reason: 'first_party_untrusted_requires_consent',
                trustTier: $trustTier,
                consentBypassAllowed: $consentBypassAllowed,
            );
        }

        if ($trustTier === SsoClient::TRUST_TIER_FIRST_PARTY_TRUSTED && ! $consentBypassAllowed) {
            return new OAuthTrustDecisionResult(
                decision: OAuthTrustDecision::ShowConsent,
                reason: 'consent_bypass_not_allowed',
                trustTier: $trustTier,
                consentBypassAllowed: false,
            );
        }

        if ($trustTier === SsoClient::TRUST_TIER_FIRST_PARTY_TRUSTED && ! $client->is_active) {
            return new OAuthTrustDecisionResult(
                decision: OAuthTrustDecision::ShowConsent,
                reason: 'inactive_client_requires_consent',
                trustTier: $trustTier,
                consentBypassAllowed: $consentBypassAllowed,
            );
        }

        if ($trustTier === SsoClient::TRUST_TIER_FIRST_PARTY_TRUSTED) {
            return new OAuthTrustDecisionResult(
                decision: OAuthTrustDecision::SkipConsent,
                reason: $requestedScopes === [] ? 'trusted_first_party_bypass_allowed' : 'trusted_first_party_bypass_allowed',
                trustTier: $trustTier,
                consentBypassAllowed: true,
            );
        }

        return new OAuthTrustDecisionResult(
            decision: OAuthTrustDecision::ShowConsent,
            reason: 'fallback_to_consent',
            trustTier: $trustTier !== '' ? $trustTier : null,
            consentBypassAllowed: $consentBypassAllowed,
        );
    }
}
