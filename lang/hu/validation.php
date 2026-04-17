<?php

return [
    'custom' => [
        'refresh_token_ttl_minutes' => [
            'gte_access_token_ttl' => 'A refresh token TTL értékének nagyobbnak vagy egyenlőnek kell lennie az access token TTL értékénél.',
        ],
        'reuse_refresh_token_forbidden' => [
            'requires_rotation' => 'A refresh token újrahasználat tiltása csak rotáció mellett engedélyezett.',
        ],
        'is_active' => [
            'default_policy_must_be_active' => 'Az alapértelmezett token szabályzatnak aktívnak kell maradnia.',
        ],
        'code_challenge_method' => [
            'required_with_code_challenge' => 'A code challenge method mező kötelező, ha a code challenge meg van adva.',
        ],
        'code_challenge' => [
            'required_with_code_challenge_method' => 'A code challenge mező kötelező, ha a code challenge method meg van adva.',
        ],
        'nonce' => [
            'required_for_openid_scope' => 'A nonce mező kötelező az openid scope használatakor.',
        ],
        'self_service_profile' => [
            'forbidden_field' => 'Ez a mező nem módosítható self-service profilmódban.',
        ],
        'self_service_password' => [
            'forbidden_field' => 'Ez a mező nem módosítható self-service jelszóváltoztatás során.',
        ],
    ],
];
