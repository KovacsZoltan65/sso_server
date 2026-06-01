<?php

namespace App\Services\OAuth;

use App\Models\SsoClient;

/**
 * OAuth redirect URI egyezés ellenőrzéséért felelős szolgáltatás.
 *
 * Az authorization code flow egyik kritikus védelmi pontja, hogy a kérésben
 * érkező redirect URI pontosan egyezzen a klienshez előre regisztrált
 * visszatérési URL-ek egyikével. Ez védi az authorization code-ot attól,
 * hogy manipulált vagy nem megbízható célra kerüljön visszairányításra.
 */
class RedirectUriMatcher
{
    /**
     * Eldönti, hogy a megadott redirect URI engedélyezett-e az adott klienshez.
     *
     * Szándékosan pontos egyezést használunk normalizált, előre regisztrált
     * URI-listával szemben. Nem végzünk részleges domain-, prefix- vagy
     * wildcard alapú illesztést, mert ezek redirect URI injection és
     * authorization code leakage kockázatot nyitnának.
     */
    public function matches(SsoClient $client, string $redirectUri): bool
    {
        return \in_array($redirectUri, $client->normalizedRedirectUris(), true);
    }
}
