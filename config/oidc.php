<?php

return [
    'issuer' => env('OIDC_ISSUER', env('APP_URL')),
    'id_token_ttl_seconds' => (int) env('OIDC_ID_TOKEN_TTL_SECONDS', 300),
    'backchannel_logout_token_ttl_seconds' => (int) env('OIDC_BACKCHANNEL_LOGOUT_TOKEN_TTL_SECONDS', env('OIDC_BACKCHANNEL_LOGOUT_TTL_SECONDS', 300)),
    'signing' => [
        'active_kid' => env('OIDC_SIGNING_ACTIVE_KID', env('OIDC_SIGNING_KID', 'oidc-signing-key-1')),
        'registry_path' => env('OIDC_SIGNING_REGISTRY_PATH', storage_path('app/oidc/signing-keys.json')),
        'key_directory' => env('OIDC_SIGNING_KEY_DIRECTORY', storage_path('app/oidc/keys')),
        'openssl_config_path' => env('OIDC_SIGNING_OPENSSL_CONFIG_PATH'),
        'retiring_grace_period_seconds' => (int) env('OIDC_SIGNING_RETIRING_GRACE_PERIOD_SECONDS', 86400),
        'keys' => array_values(array_filter([
            env('OIDC_SIGNING_PRIVATE_KEY_PATH') || env('OIDC_SIGNING_PUBLIC_KEY_PATH') || env('OIDC_SIGNING_KID')
                ? [
                    'kid' => env('OIDC_SIGNING_KID', 'oidc-signing-key-1'),
                    'status' => 'active',
                    'alg' => env('OIDC_SIGNING_ALG', 'RS256'),
                    'private_key_path' => env('OIDC_SIGNING_PRIVATE_KEY_PATH'),
                    'public_key_path' => env('OIDC_SIGNING_PUBLIC_KEY_PATH'),
                    'published' => true,
                ]
                : null,
            env('OIDC_SIGNING_LEGACY_KID') || env('OIDC_SIGNING_LEGACY_PUBLIC_KEY_PATH')
                ? [
                    'kid' => env('OIDC_SIGNING_LEGACY_KID'),
                    'status' => env('OIDC_SIGNING_LEGACY_STATUS', 'retiring'),
                    'alg' => env('OIDC_SIGNING_LEGACY_ALG', env('OIDC_SIGNING_ALG', 'RS256')),
                    'private_key_path' => env('OIDC_SIGNING_LEGACY_PRIVATE_KEY_PATH'),
                    'public_key_path' => env('OIDC_SIGNING_LEGACY_PUBLIC_KEY_PATH'),
                    'published' => filter_var(env('OIDC_SIGNING_LEGACY_PUBLISHED', true), FILTER_VALIDATE_BOOL),
                ]
                : null,
        ])),
    ],
];
