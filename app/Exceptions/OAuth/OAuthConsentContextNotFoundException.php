<?php

namespace App\Exceptions\OAuth;

use RuntimeException;

class OAuthConsentContextNotFoundException extends RuntimeException
{
    public static function missingOrExpired(): self
    {
        return new self('The consent context is missing, expired, or no longer available.');
    }
}
