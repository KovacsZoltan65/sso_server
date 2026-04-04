<?php

namespace App\Data\OAuth;

use App\Models\UserClientConsent;

final class OAuthRememberedConsentDecisionResult
{
    public function __construct(
        public readonly bool $shouldReuse,
        public readonly string $reason,
        public readonly ?UserClientConsent $consent = null,
    ) {
    }
}
