<?php

return [
    'oauth' => [
        'authorization_denied' => 'OAuth authorization denied.',
        'authentication_failed' => 'Authentication failed.',
        'userinfo' => [
            'retrieved' => 'User info retrieved successfully.',
        ],
        'token' => [
            'issued' => 'OAuth token issued successfully.',
            'request_failed' => 'OAuth token request failed.',
        ],
        'revoke' => [
            'request_failed' => 'OAuth token revoke request failed.',
        ],
        'introspect' => [
            'completed' => 'Token introspection completed.',
            'failed' => 'Token introspection failed.',
        ],
        'consent' => [
            'review_description' => 'Review the requested permissions before deciding whether to continue.',
            'token_invalid' => 'The consent decision is missing, expired, or no longer valid.',
            'token_user_mismatch' => 'The consent decision does not belong to the current user session.',
            'token_client_invalid' => 'The consent decision is no longer valid for this client.',
        ],
        'client_invalid_or_inactive' => 'The provided client is invalid or inactive.',
        'redirect_uri_mismatch' => 'The redirect URI does not match the registered client redirect URIs.',
        'scope_not_allowed' => 'The requested scope [:scope] is not allowed for this client.',
        'pkce_required' => 'PKCE is required for this client.',
        'code_challenge_method_s256' => 'The code challenge method must be S256.',
    ],
    'clients' => [
        'created' => 'SSO client created successfully.',
        'updated' => 'SSO client updated successfully.',
        'deleted' => 'SSO client deleted successfully.',
        'secret_rotated' => 'Client secret rotated successfully.',
        'secret_revoked' => 'Client secret revoked successfully.',
    ],
    'users' => [
        'created' => 'User created successfully.',
        'updated' => 'User updated successfully.',
        'deleted' => 'User deleted successfully.',
        'bulk_deleted' => 'Selected users deleted successfully.',
    ],
    'roles' => [
        'created' => 'Role created successfully.',
        'updated' => 'Role updated successfully.',
        'deleted' => 'Role deleted successfully.',
        'bulk_deleted' => 'Selected roles deleted successfully.',
    ],
    'permissions' => [
        'created' => 'Permission created successfully.',
        'updated' => 'Permission updated successfully.',
        'deleted' => 'Permission deleted successfully.',
        'bulk_deleted' => 'Selected permissions deleted successfully.',
    ],
    'scopes' => [
        'created' => 'Scope created successfully.',
        'updated' => 'Scope updated successfully.',
        'deleted' => 'Scope deleted successfully.',
        'bulk_deleted' => 'Selected scopes deleted successfully.',
    ],
    'token_policies' => [
        'created' => 'Token policy created successfully.',
        'updated' => 'Token policy updated successfully.',
        'deleted' => 'Token policy deleted successfully.',
        'bulk_deleted' => 'Selected token policies deleted successfully.',
    ],
    'tokens' => [
        'revoked' => 'Token revoked successfully.',
        'family_not_found' => 'Token family not found.',
        'family_revoked' => 'Token family revoked successfully.',
        'family_already_revoked' => 'Token family was already revoked.',
    ],
    'client_user_access' => [
        'records_retrieved' => 'Client user access records retrieved successfully.',
        'created' => 'Client user access created successfully.',
        'updated' => 'Client user access updated successfully.',
        'deleted' => 'Client user access deleted successfully.',
        'bulk_deleted' => 'Selected client user access records deleted successfully.',
        'client_assignments_retrieved' => 'Client access assignments retrieved successfully.',
        'user_assignments_retrieved' => 'User client access assignments retrieved successfully.',
    ],
];
