<?php

return [
    'custom' => [
        'refresh_token_ttl_minutes' => [
            'gte_access_token_ttl' => 'Refresh token TTL must be greater than or equal to access token TTL.',
        ],
        'reuse_refresh_token_forbidden' => [
            'requires_rotation' => 'Refresh token reuse can only be forbidden when rotation is enabled.',
        ],
        'is_active' => [
            'default_policy_must_be_active' => 'The default token policy must remain active.',
        ],
        'code_challenge_method' => [
            'required_with_code_challenge' => 'The code challenge method field is required when code challenge is present.',
        ],
        'code_challenge' => [
            'required_with_code_challenge_method' => 'The code challenge field is required when code challenge method is present.',
        ],
        'nonce' => [
            'required_for_openid_scope' => 'The nonce field is required when requesting the openid scope.',
        ],
        'self_service_profile' => [
            'forbidden_field' => 'This field cannot be updated through self-service profile.',
        ],
        'self_service_password' => [
            'forbidden_field' => 'This field cannot be updated through self-service password change.',
        ],
    ],
];
