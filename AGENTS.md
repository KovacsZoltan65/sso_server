# AGENTS.md

# SSO_SERVER – AI Development Rules

You are working on the **SSO_SERVER** project.

This application is the **central authentication and authorization service** of a future multi-client SSO ecosystem.

Your job is to help build the system in a way that is:

- secure
- scalable
- maintainable
- extensible toward OAuth2 / OpenID Connect style flows

---

# 1. Project Mission

The SSO server is responsible for:

- user authentication
- client application registration and management
- redirect URI validation
- scope management
- issuing access and refresh tokens
- token policy enforcement
- token revocation
- secret handling
- audit logging of critical auth events

This is a **security-critical application**.

Every implementation decision must prioritize:

1. security
2. correctness
3. traceability
4. maintainability
5. future protocol extensibility

---

# 2. Core Stack

Use the following stack and conventions unless explicitly instructed otherwise:

- Laravel 13
- PHP 8.4+
- Vue 3
- Vite
- Inertia.js
- PrimeVue
- MySQL
- Laravel Breeze
- prettus/l5-repository
- spatie/laravel-activitylog
- spatie/laravel-data
- spatie/laravel-permission
- laradumps/laradumps (dev only)

Do not introduce random new libraries without strong justification.

Prefer Laravel-native solutions first.

---

# 3. Architecture Rules

Follow this backend structure strictly:

- **Controller → Service → Repository**
- Controllers must stay thin
- Business logic belongs in Services
- Data access and filtering belong in Repositories
- Validation must use **FormRequest**
- Authorization must use **Policies**
- API output must use **Resources** or a consistent JSON response format
- Eloquent relationships must be explicit and clean
- No business logic inside Controllers
- No query-heavy logic inside Controllers
- No validation logic scattered across Services or Controllers

## Required responsibilities by layer

### Controllers

Allowed:

- receive request
- call FormRequest validation
- call Service
- return response

Not allowed:

- complex branching
- business rules
- direct database-heavy logic

### Services

Allowed:

- business rules
- orchestration
- security decisions
- transactional flows
- token issuing/revocation logic
- secret lifecycle handling

### Repositories

Allowed:

- queries
- filters
- pagination
- lookup methods
- eager loading strategy

Not allowed:

- business decisions
- authorization logic

---

# 4. Security Rules (STRICT)

These rules are mandatory.

## Secrets

- Never store plain client secrets
- Never return stored secret hashes in API responses
- Client secrets must be hashed before persistence
- A generated secret may be shown **only once at creation time**
- Never log secrets
- Never expose secrets in exceptions, validation messages, or debug output

## Redirect URIs

- Redirect URI matching must be strict
- Never allow loose matching
- Never allow wildcard shortcuts unless explicitly designed and approved
- Validation must compare against registered redirect URIs only

## Tokens

- Support access token revocation
- Support refresh token revocation
- Token issuance rules must be explicit
- Token TTL must be policy-driven
- Refresh token rotation must be supported by design
- PKCE support must be considered in the architecture even if not fully implemented initially
- Never leak tokens in logs
- Never expose internal token metadata unnecessarily

## Input validation

- Validate all input
- Reject malformed client requests
- Reject unknown or unauthorized scopes
- Reject invalid redirect URIs
- Reject inactive or revoked clients
- Reject expired or revoked tokens

## Logging / audit

- Log all critical authentication and token events
- Log failed login attempts
- Log client auth failures
- Log token issuance
- Log token refresh
- Log token revocation
- Log secret rotation
- Log suspicious validation failures when relevant

## Responses

- Never expose internal stack traces
- Never expose security-sensitive details in production responses
- Use safe, minimal error messages

---

# 5. Domain Model Requirements

The database design must support at least:

- `sso_clients`
- `redirect_uris`
- `scopes`
- `client_scopes`
- `client_secrets`
- `token_policies`
- `tokens`
- `audit_logs`

## Expected conceptual responsibilities

### sso_clients

Stores registered client applications.

Suggested fields:

- id
- name
- client_id
- client_type
- is_active
- description
- created_by
- timestamps

### redirect_uris

Stores allowed redirect URIs per client.

Suggested fields:

- id
- sso_client_id
- uri
- is_primary
- timestamps

### scopes

Stores available scopes.

Suggested fields:

- id
- name
- description
- is_active
- timestamps

### client_scopes

Pivot table connecting clients and allowed scopes.

Suggested fields:

- id or composite structure
- sso_client_id
- scope_id

### client_secrets

Stores hashed client secrets and metadata.

Suggested fields:

- id
- sso_client_id
- secret_hash
- name
- last_used_at
- expires_at
- revoked_at
- created_by
- timestamps

### token_policies

Stores token policy configuration.

Suggested fields:

- id
- sso_client_id
- access_token_ttl
- refresh_token_ttl
- allow_refresh
- require_pkce
- rotate_refresh_token
- timestamps

### tokens

Stores issued token records and lifecycle state.

Suggested fields:

- id
- sso_client_id
- user_id
- token_type
- token_identifier
- scopes_json or normalized relation
- expires_at
- revoked_at
- last_used_at
- meta
- timestamps

### audit_logs

Stores important system and auth events.

Suggested fields:

- id
- actor_type
- actor_id
- event_type
- target_type
- target_id
- ip_address
- user_agent
- context_json
- created_at

---

# 6. API Rules

All API responses must follow a consistent structure:

```json
{
    "message": "string",
    "data": {},
    "meta": {},
    "errors": {}
}
```

Status code expectations
200 OK
201 Created
204 No Content
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
422 Validation Error
Consistency requirements
Do not mix random response formats
Validation errors must be consistent everywhere
Authorization failures must be consistent everywhere
Authentication failures must be consistent everywhere 7. Backend Development Rules
Validation
Always create dedicated FormRequest classes
Keep validation rules explicit
Prefer whitelist validation
Validate enums and booleans strictly
Validate URIs carefully
Validate TTL values and policy fields carefully
Authorization
Always use Policies for protected resources
Never bury permission checks in random places
Policies must cover CRUD and security-sensitive actions
Models
Use casts where appropriate
Use guarded/fillable consistently
Define relationships clearly
Avoid fat models with hidden business logic
Services

Typical services may include:

AuthenticationService
ClientService
ClientSecretService
RedirectUriService
ScopeService
TokenService
TokenPolicyService
AuditLogService
Repositories

Typical repositories may include:

ClientRepository
RedirectUriRepository
ScopeRepository
TokenRepository
TokenPolicyRepository
AuditLogRepository
Transactions

Use database transactions for flows like:

creating client + redirect URIs + token policy
secret generation + persistence + audit logging
token issuance + audit logging
token revocation chains 8. Frontend Rules

Use:

Vue 3 Composition API

<script setup>
PrimeVue components only
UI rules
DataTable for all admin lists
Dialogs for CRUD where reasonable
Toast required for user feedback
ConfirmDialog required for destructive actions
Avoid giant monolithic components
Separate UI logic from API/service logic
Prefer modular page structure
Sensitive UX rules
Secrets visible only once at creation
Audit logs are read-only
Dangerous actions must require confirmation
Error messages must be user-friendly but safe
Do not display sensitive backend internals
Suggested admin pages
Clients
Redirect URIs
Scopes
Token Policies
Secrets
Audit Logs
Tokens / Revocations
9. Logging and Audit Standards

Audit logging is mandatory for critical events.

At minimum, capture events such as:

user login success
user login failure
client creation
client update
client deactivation
secret creation
secret revocation
token issuance
token refresh
token revocation
invalid redirect URI attempt
invalid scope request
authorization failures where relevant

Audit logs must be:

structured
queryable
safe
append-oriented
non-editable from admin UI
10. Testing Rules

Testing is required for critical flows.

Must-have feature tests
authentication flow
client CRUD
redirect URI validation
scope assignment
secret generation and secure storage
token issuance
token refresh
token revocation
policy authorization
invalid input rejection
revoked/inactive client behavior
Test principles
test security-sensitive paths first
test both success and failure cases
test validation thoroughly
test policies explicitly
test secret invisibility in responses
test redirect URI strict matching
test revocation behavior
11. Implementation Priorities

When building features, prefer this order:

foundations and schema
client management
redirect URI management
scope management
token policy management
secret management
authentication/token issuance flow
refresh/revocation flow
audit log UI
hardening and tests
12. Coding Style Rules
Follow Laravel conventions
Prefer clarity over cleverness
Do not over-engineer
Keep files focused
Use meaningful names
Avoid magic values
Extract reusable logic carefully
Keep security-sensitive code easy to audit
13. Forbidden Practices

Do NOT:

store plain secrets
expose secrets in API responses
put business logic in Controllers
skip FormRequest validation
skip Policies
use loose redirect URI validation
return inconsistent JSON structures
create giant Vue components with mixed concerns
log tokens or secrets
introduce unreviewed auth shortcuts
silently ignore revocation or expiration states
14. Future Compatibility Goal

This system must remain extendable toward:

OAuth2
OpenID Connect
multi-client SSO ecosystem
PKCE-based public clients
token introspection
consent/scope approval flows
client credential variations

Even if these are not fully implemented now, do not design the system in a way that blocks them later.

15. Expected Assistant Behavior

When implementing anything in this repository:

Respect all architecture rules
Respect all security rules
Keep Controllers thin
Use FormRequests and Policies
Use Services and Repositories correctly
Keep responses consistent
Avoid sensitive data leakage
Write tests for critical flows
If a requirement is unclear or conflicts with security, stop and ask first

When in doubt:

choose the safer implementation
choose the more explicit validation
choose the more maintainable structure
16. Output Quality Standard

Every generated implementation should aim to be:

production-ready
security-conscious
internally consistent
easy to review
easy to extend later

The code should look like it belongs in a serious authentication system, not in a demo project.
