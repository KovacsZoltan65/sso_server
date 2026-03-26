<?php

namespace App\Services\OAuth;

use App\Models\SsoClient;

class RedirectUriMatcher
{
    public function matches(SsoClient $client, string $redirectUri): bool
    {
        $candidate = trim($redirectUri);

        return in_array($candidate, $client->normalizedRedirectUris(), true);
    }
}
