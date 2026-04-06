<?php

return [
    'issuer' => env('OIDC_ISSUER', env('APP_URL')),
    'id_token_ttl_seconds' => (int) env('OIDC_ID_TOKEN_TTL_SECONDS', 300),
    'signing' => [
        'alg' => env('OIDC_SIGNING_ALG', 'RS256'),
        'kid' => env('OIDC_SIGNING_KID', 'oidc-signing-key-1'),
        'private_key_path' => env('OIDC_SIGNING_PRIVATE_KEY_PATH'),
        'public_key_path' => env('OIDC_SIGNING_PUBLIC_KEY_PATH'),
    ],
];
