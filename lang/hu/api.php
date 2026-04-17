<?php

return [
    'oauth' => [
        'authorization_denied' => 'OAuth autorizáció elutasítva.',
        'authentication_failed' => 'Hitelesítés sikertelen.',
        'userinfo' => [
            'retrieved' => 'A felhasználói adatok lekérése sikeres.',
        ],
        'token' => [
            'issued' => 'OAuth token sikeresen kiadva.',
            'request_failed' => 'Az OAuth token kérés sikertelen.',
        ],
        'revoke' => [
            'request_failed' => 'Az OAuth token visszavonási kérés sikertelen.',
        ],
        'introspect' => [
            'completed' => 'A token introspekció sikeres.',
            'failed' => 'A token introspekció sikertelen.',
        ],
        'consent' => [
            'review_description' => 'Nézd át a kért jogosultságokat a döntés előtt.',
            'token_invalid' => 'A consent döntés hiányzik, lejárt, vagy már nem érvényes.',
            'token_user_mismatch' => 'A consent döntés nem az aktuális felhasználói munkamenethez tartozik.',
            'token_client_invalid' => 'A consent döntés ehhez a klienshez már nem érvényes.',
        ],
        'client_invalid_or_inactive' => 'A megadott kliens érvénytelen vagy inaktív.',
        'redirect_uri_mismatch' => 'A redirect URI nem egyezik a regisztrált kliens redirect URI-val.',
        'scope_not_allowed' => 'A kért [:scope] scope nem engedélyezett ennél a kliensnél.',
        'pkce_required' => 'Ehhez a klienshez kötelező a PKCE.',
        'code_challenge_method_s256' => 'A code challenge method értéke csak S256 lehet.',
    ],
    'clients' => [
        'created' => 'SSO kliens sikeresen létrehozva.',
        'updated' => 'SSO kliens sikeresen frissítve.',
        'deleted' => 'SSO kliens sikeresen törölve.',
        'secret_rotated' => 'A kliens titok sikeresen rotálva.',
        'secret_revoked' => 'A kliens titok sikeresen visszavonva.',
    ],
    'users' => [
        'created' => 'Felhasználó sikeresen létrehozva.',
        'updated' => 'Felhasználó sikeresen frissítve.',
        'deleted' => 'Felhasználó sikeresen törölve.',
        'bulk_deleted' => 'A kijelölt felhasználók sikeresen törölve.',
    ],
    'roles' => [
        'created' => 'Szerepkör sikeresen létrehozva.',
        'updated' => 'Szerepkör sikeresen frissítve.',
        'deleted' => 'Szerepkör sikeresen törölve.',
        'bulk_deleted' => 'A kijelölt szerepkörök sikeresen törölve.',
    ],
    'permissions' => [
        'created' => 'Jogosultság sikeresen létrehozva.',
        'updated' => 'Jogosultság sikeresen frissítve.',
        'deleted' => 'Jogosultság sikeresen törölve.',
        'bulk_deleted' => 'A kijelölt jogosultságok sikeresen törölve.',
    ],
    'scopes' => [
        'created' => 'Scope sikeresen létrehozva.',
        'updated' => 'Scope sikeresen frissítve.',
        'deleted' => 'Scope sikeresen törölve.',
        'bulk_deleted' => 'A kijelölt scope-ok sikeresen törölve.',
    ],
    'token_policies' => [
        'created' => 'Token szabályzat sikeresen létrehozva.',
        'updated' => 'Token szabályzat sikeresen frissítve.',
        'deleted' => 'Token szabályzat sikeresen törölve.',
        'bulk_deleted' => 'A kijelölt token szabályzatok sikeresen törölve.',
    ],
    'tokens' => [
        'revoked' => 'A token sikeresen visszavonva.',
        'family_not_found' => 'A token család nem található.',
        'family_revoked' => 'A token család sikeresen visszavonva.',
        'family_already_revoked' => 'A token család már korábban visszavonásra került.',
    ],
    'client_user_access' => [
        'records_retrieved' => 'A kliens-felhasználó hozzáférési rekordok sikeresen lekérve.',
        'created' => 'A kliens-felhasználó hozzáférés sikeresen létrehozva.',
        'updated' => 'A kliens-felhasználó hozzáférés sikeresen frissítve.',
        'deleted' => 'A kliens-felhasználó hozzáférés sikeresen törölve.',
        'bulk_deleted' => 'A kijelölt kliens-felhasználó hozzáférések sikeresen törölve.',
        'client_assignments_retrieved' => 'A kliens-hozzárendelések sikeresen lekérve.',
        'user_assignments_retrieved' => 'A felhasználó kliens-hozzárendelései sikeresen lekérve.',
    ],
];
