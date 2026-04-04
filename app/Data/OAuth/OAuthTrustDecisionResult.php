<?php

namespace App\Data\OAuth;

use App\Enums\OAuth\OAuthTrustDecision;

final class OAuthTrustDecisionResult
{
    public function __construct(
        public readonly OAuthTrustDecision $decision,
        public readonly string $reason,
        public readonly ?string $trustTier,
        public readonly bool $consentBypassAllowed,
    ) {
    }

    public function shouldShowConsent(): bool
    {
        return $this->decision === OAuthTrustDecision::ShowConsent;
    }

    public function shouldSkipConsent(): bool
    {
        return $this->decision === OAuthTrustDecision::SkipConsent;
    }

    public function shouldDenyAuthorization(): bool
    {
        return $this->decision === OAuthTrustDecision::DenyAuthorization;
    }
}
