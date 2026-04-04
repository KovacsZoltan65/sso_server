<?php

namespace App\Enums\OAuth;

enum OAuthTrustDecision: string
{
    case ShowConsent = 'show_consent';
    case SkipConsent = 'skip_consent';
    case DenyAuthorization = 'deny_authorization';
}
