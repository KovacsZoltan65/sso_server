<?php

return [
    'issuer' => env('OIDC_ISSUER', env('APP_URL')),
    'id_token_ttl_seconds' => (int) env('OIDC_ID_TOKEN_TTL_SECONDS', 300),
    'signing' => [
        'active_kid' => env('OIDC_SIGNING_ACTIVE_KID', env('OIDC_SIGNING_KID', 'oidc-signing-key-1')),
        'keys' => array_values(array_filter([
            env('OIDC_SIGNING_PRIVATE_KEY_PATH') || env('OIDC_SIGNING_PUBLIC_KEY_PATH') || env('OIDC_SIGNING_KID')
                ? [
                    'kid' => env('OIDC_SIGNING_KID', 'oidc-signing-key-1'),
                    'alg' => env('OIDC_SIGNING_ALG', 'RS256'),
                    'private_key_path' => env('OIDC_SIGNING_PRIVATE_KEY_PATH'),
                    'public_key_path' => env('OIDC_SIGNING_PUBLIC_KEY_PATH'),
                    'published' => true,
                ]
                : null,
            env('OIDC_SIGNING_LEGACY_KID') || env('OIDC_SIGNING_LEGACY_PUBLIC_KEY_PATH')
                ? [
                    'kid' => env('OIDC_SIGNING_LEGACY_KID'),
                    'alg' => env('OIDC_SIGNING_LEGACY_ALG', env('OIDC_SIGNING_ALG', 'RS256')),
                    'private_key_path' => env('OIDC_SIGNING_LEGACY_PRIVATE_KEY_PATH'),
                    'public_key_path' => env('OIDC_SIGNING_LEGACY_PUBLIC_KEY_PATH'),
                    'published' => filter_var(env('OIDC_SIGNING_LEGACY_PUBLISHED', true), FILTER_VALIDATE_BOOL),
                ]
                : null,
        ])),
    ],
];
