export const adminPlaceholders = {
    roles: [
        'Prepare role CRUD, role hierarchy, and assignment screens.',
        'Add immutable system-role protection and audit logging for updates.',
        'Connect role management to policy-aware UI actions.',
    ],
    permissions: [
        'Split SSO permissions into create, update, delete, rotate, and revoke actions.',
        'Add permission bundles for support and security operations.',
        'Map controller and policy checks to the seeded capability set.',
    ],
    'sso-clients': [
        'Create client registration flow with strict redirect URI validation.',
        'Add secret rotation, reveal-once UX, and ownership metadata.',
        'Attach scopes and token policy references to each client record.',
    ],
    scopes: [
        'Store scope metadata, descriptions, and consent requirements.',
        'Prepare client-scope assignments and default grants.',
        'Expose read-models for future client configuration screens.',
    ],
    'token-policies': [
        'Configure access token, refresh token, and auth code lifetimes.',
        'Define signing and revocation strategy per environment.',
        'Prepare policy assignment rules for clients and trust tiers.',
    ],
    'audit-logs': [
        'Expand activity categories for authentication, authorization, and SSO changes.',
        'Add filters by actor, entity, and event type.',
        'Prepare export and retention policy management.',
    ],
};
