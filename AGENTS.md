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

# 4. Admin List / DataTable Standard

All PrimeVue-based admin lists must follow one consistent table pattern across current and future modules.

- Use **PrimeVue DataTable** for admin lists
- Use a shared **RowActionMenu** for row-level actions instead of separate inline button bars
- Required row actions: **Edit** and **Delete**
- If an entity is deletable, support **bulk delete** as part of the table flow
- Bulk delete must always include:
  - selection handling
  - confirmation dialog
  - success or error toast
  - table refresh
  - selection reset after success
- Destructive actions must always use **ConfirmDialog**
- After create, update, delete, or bulk delete, the list state must be refreshed and remain consistent
- Stale table state or partially refreshed CRUD flows are not acceptable
- Delete restrictions must be enforced in the backend as well, not only hidden or disabled in the frontend
- New admin modules must follow the same shared table standard instead of creating a new local CRUD table pattern
- Admin list pages must use a full-height layout chain so the table fills the available vertical space
- The body must not be the primary scroll container for admin tables; scrolling must happen inside the table card or DataTable container
- Use a card structure where the content area is `flex-1` plus `overflow-hidden`
- Use scrollable PrimeVue DataTable configuration with flex-based height for full-height admin tables
- The flex chain must stay valid through layout, page wrapper, card, card body, card content, and the DataTable wrapper using `flex`, `flex-col`, `flex-1`, and `min-h-0`
- It is not acceptable for the card to grow while the DataTable stays visually collapsed at the top
- Large empty space under the paginator is a layout bug and must be fixed at the shared layout or DataTable wrapper level
- Overlay-based row actions must not be clipped by card or table overflow; use a non-clipped overlay strategy
- Simple mini-CRUD flows may use modals, but complex admin editing must use separate Create/Edit pages
- If a form contains many fields, relation-heavy assignment, or large selectors, it must not stay in a modal
- Role and permission-heavy administration should default to separate-page Create/Edit flows
- Client administration with redirect URIs, scopes, or token policy editing must use separate Create/Edit pages, not modal variants
- Scope catalog management must use page-based Create/Edit with a shared ScopeForm, and scope codes must remain unique technical identifiers
- Token policy administration is security-critical and must use page-based Create/Edit with backend-enforced default uniqueness and TTL validation
- Admin lists with non-trivial datasets must implement a visible, working paginator with PrimeVue DataTable lazy state and backend pagination meta kept in sync
- It is not acceptable for a list to fetch paginated data while omitting or breaking the paginator UI
- Search, sort, page changes, and rows-per-page changes must work together without desynchronizing the table state
- Delete and bulk delete flows must not leave the paginator on an empty orphaned page
- Large relation selectors must not use MultiSelect
- Role-permission editing must use a grouped, searchable, resource-sectioned checkbox editor
- Client scopes selectors must reuse the shared grouped checkbox editor pattern instead of introducing a separate implementation
- Complex modal forms must fall back to one column below wide desktop breakpoints, and grouped checkbox selectors must not introduce horizontal or nested scroll
- Selector UI that overflows the viewport or becomes unreadable at larger data volume is not acceptable
- Create and Edit admin pages with larger forms must use a full-height flex layout with a fixed header, a scrollable form content area, and fixed action buttons
- The body must not be the primary scroll container for larger admin forms; scrolling must stay inside the card content area
- A form card that overflows the viewport and pushes primary actions off-screen is a layout bug and must be fixed in the shared page/card structure
- Create and Edit form logic must live in a shared form component when the same entity supports both page and modal flows
- Modal and page variants must reuse the same form fields and validation rendering; duplicated form logic is not acceptable
- Secrets may only be shown once at creation time and must never be returned again in plain text from edit or read flows

Implementation expectations:

- Keep list orchestration on the index page
- Reuse shared toolbar, row action, selection, and refresh patterns where practical
- Keep backend responses consistent for CRUD and bulk actions
- Keep authorization policy-based
- Keep validation in FormRequests

---

# Permission Standard

- Every resource permission must use the `resource.action` format
- Minimum required actions for every resource: `viewAny`, `view`, `create`, `update`, `delete`, `deleteAny`
- Bulk operations must always have their own permission such as `deleteAny`
- Domain-specific actions must exist as separate permissions, not be folded into generic CRUD permissions
- Permission seeders must generate permissions from a central definition, not scattered hardcoded strings
- Legacy compatibility aliases may exist temporarily, but new permission design must follow the standard naming only

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

Testing is mandatory for all meaningful feature work. A change is not complete until the required automated tests exist, are updated for the new behavior, and pass locally.

Test tooling rule

- Verify backend and frontend test tooling before implementing non-trivial work
- If a required test package, config file, setup file, or script is missing or broken, fix that first
- Do not leave Pest, PHPUnit, Vitest, jsdom, Vue Test Utils, or related setup half-configured
- Frontend interactive work is not complete without a working Vitest setup
- Backend feature work is not complete without a working Laravel test setup

Backend test stack

- Use the existing Laravel test stack
- Prefer Pest for new backend tests
- Keep PHPUnit compatibility intact
- Do not replace the established backend test style without a strong reason

Frontend test stack

- Use Vitest for frontend tests
- Use Vue Test Utils for Vue component and page tests
- Do not introduce alternate frontend test frameworks without explicit approval
- Reuse the existing Vitest mocks, setup helpers, and stubbing patterns where practical
- Frontend selector strategy: follow `docs/frontend-test-selector-guideline.md` and prefer stable selectors over locale/copy-dependent ones

Mandatory backend coverage

- authentication and authorization flows
- policy enforcement
- CRUD happy paths
- validation failures
- authorization failures
- redirect / flash response behavior
- Inertia response payload shape where the frontend depends on it
- destructive action protections
- security-sensitive flows such as secrets, redirect URI validation, token issuance, refresh, revocation, and access control

Mandatory frontend coverage

- interactive admin pages that coordinate CRUD flows
- modal open / close behavior
- create and edit submission behavior
- field-level validation rendering
- destructive action confirmation flows where practical
- shared form partial contracts
- page-to-modal state synchronization
- modal-first CRUD pages such as Users, Roles, and Permissions
- paginator, refresh, row-action, and orphan-page recovery behavior for shared admin tables
- create-page and edit-page orchestration for page-based CRUD modules such as Clients, Scopes, and Token Policies

CRUD rule

- Every CRUD module must have backend tests for index, happy-path mutations, validation failures, and authorization failures
- Every interactive CRUD frontend must have Vitest coverage for its primary create/edit/delete orchestration and shared form behavior
- If a module has delete restrictions or relation-sensitive rules, those rules must be explicitly tested
- If a module supports bulk delete, bulk delete success, validation, authorization, and protected-record behavior must be tested
- If a module uses modal-first flows, tests must cover modal open/close, selected-record hydration, submit behavior, and list refresh expectations
- If a module uses page-based create/edit flows, tests must cover page payload, form submission, validation rendering, and navigation or redirect behavior

Maintenance rule

- When behavior changes, update the existing tests in the same task
- Do not leave outdated tests behind
- Do not delete useful failing tests to get green output; fix the implementation or replace the test with a better one that covers the real behavior
- Every bug fix must add or update at least one regression test unless the failure is fully covered already
- If the audit finds a critical gap in coverage for touched code, fill that gap in the same task

Execution rule

- Run the relevant backend tests after backend changes
- Run the relevant frontend Vitest suite after frontend changes
- After cross-cutting or CRUD work, run both backend and frontend tests
- For repo-wide test hardening, shared infrastructure changes, auth changes, or cross-cutting contract work, run the full backend suite and the full frontend Vitest suite before closing the task when feasible
- Report the commands used and whether the suites passed
- Do not claim completion while known relevant tests are failing
- If a full affected suite is too expensive to run, run the narrowest relevant suite first, then the broader project suite before closing the task when feasible
- Failing tests must be investigated and fixed at the root cause; they must not be skipped or ignored without explicit approval
- If backend and frontend contracts interact, add or update tests on both sides in the same task so the contract is enforced end to end

Required maintenance behavior

- Treat missing test tooling as a blocking issue: install or repair the required backend or frontend test dependencies before feature work continues
- Do not merge or declare completion on behavior that changed without the corresponding backend or frontend tests being updated
- When adding a new interactive page, modal flow, or security-sensitive endpoint, add the initial tests in the same task instead of leaving testing as follow-up work

Test principles

- test security-sensitive paths first
- test success and failure cases
- test validation thoroughly
- test policies explicitly
- test secret invisibility in responses
- test redirect URI strict matching
- test revocation behavior
- prefer focused, high-value tests over noisy low-value assertions
- prefer assertions that verify user-visible behavior, authorization outcomes, and data integrity over internal implementation details
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
Write and maintain the required backend and frontend tests
Run the relevant test suites before declaring the work complete
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
