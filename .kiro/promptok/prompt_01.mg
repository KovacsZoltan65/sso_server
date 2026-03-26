You are working on a Laravel 13 + Vue 3 + Vite + Inertia + PrimeVue SSO SERVER project.

Language rules:
- Explanations, comments, commit-style summaries, and progress notes must be in Hungarian.
- All code must be in English.

Your task:
Refactor the SSO Client domain to normalize client storage and prepare the system for real OAuth2 / OpenID Connect style flows.

IMPORTANT:
This is a SECURITY-CRITICAL refactor.
Do not over-engineer.
Do not introduce random libraries.
Follow Laravel conventions strictly.
Keep controllers thin.
Business logic belongs in Services.
Queries and filtering belong in Repositories.
Always use FormRequest validation.
Always use Policies for authorization.
Use Resources or consistent JSON/Inertia payload structures.
Never expose sensitive secrets in API responses.
Never store plain secrets.
Treat redirect URI validation as strict and security-sensitive.

---

# CURRENT PROBLEM

The current SSO client storage is too denormalized:
- `sso_clients.redirect_uris` is stored as JSON
- `sso_clients.scopes` is stored as JSON
- `sso_clients.client_secret_hash` is stored directly on the client row

This is acceptable for an early prototype but not for a secure, scalable SSO server.

We need to normalize the storage so the system can later support:
- multiple redirect URIs
- multiple scopes via pivot
- multiple client secrets with lifecycle management
- secret rotation
- secret revocation
- auditability
- better future support for authorization code / token flows

---

# GOAL

Refactor the domain so that the following structure becomes the new source of truth:

## Tables
- `sso_clients`
- `redirect_uris`
- `scopes`
- `client_scopes` (pivot)
- `client_secrets`
- `token_policies`

Prepare the design so that future tables can be added cleanly:
- `authorization_codes`
- `tokens`
- `refresh_tokens`
- `audit_logs` enhancements

---

# HARD REQUIREMENTS

## Architecture
Follow:
- Controller → Service → Repository pattern
- FormRequest validation
- Policy authorization
- Thin controllers
- Business logic only in Services
- Repositories handle queries and filtering
- Use Eloquent relationships cleanly
- Keep implementation production-ready and readable

## Security
- Never store plain secrets
- Only show plain secret once at creation / rotation time
- Store only secret hash in DB
- Redirect URI matching must be strict
- Normalize and validate URI input carefully
- Do not expose secret hashes unnecessarily in responses
- Add audit log events for critical secret actions
- Preserve secure defaults

## Frontend
- Vue 3 Composition API with `<script setup>`
- PrimeVue only
- DataTable for admin lists
- Dialogs for CRUD where appropriate
- Toast + ConfirmDialog required
- Avoid giant monolithic components
- Secrets visible only once after creation/rotation
- Audit logs read-only if touched

## Response/API style
Use consistent structure:
- `{ message, data, meta, errors }`
and proper status codes.

---

# REQUIRED DOMAIN CHANGES

## 1. Normalize redirect URIs
Create a dedicated `redirect_uris` table.

Suggested fields:
- id
- sso_client_id
- uri
- is_primary (boolean, optional if useful)
- created_at
- updated_at

Rules:
- each row belongs to one client
- per-client uniqueness on URI
- strict validation
- trim whitespace
- no empty values
- no duplicates in create/update payload
- reject malformed URIs
- exact-match semantics must be possible later

Add:
- Eloquent model
- relationship on `SsoClient`
- repository/service handling for sync/update

## 2. Normalize client scopes
Use a dedicated pivot table `client_scopes`.

Rules:
- each client may have many scopes
- only existing scopes may be attached
- duplicates must be prevented
- sync behavior must be deterministic
- future token issuance must be able to derive allowed scopes cleanly

Add:
- relationship on `SsoClient`
- sync logic in Service layer
- validation in FormRequest

## 3. Normalize client secrets
Create a dedicated `client_secrets` table.

Suggested fields:
- id
- sso_client_id
- name (nullable, optional label like "Initial secret", "Rotated 2026-03", etc.)
- secret_hash
- last_four (optional but useful for admin UX)
- is_active
- revoked_at nullable
- expires_at nullable
- created_by nullable foreign key if project conventions allow
- created_at
- updated_at

Rules:
- store only hash
- never store plain secret
- plain secret may only be generated and returned once at creation/rotation time
- support multiple secrets over time
- future rotation must be possible
- future revocation must be possible
- only active, non-revoked secrets should be considered usable later

Add:
- Eloquent model
- relationship on `SsoClient`
- dedicated secret generation + hashing service logic
- safe one-time response handling

## 4. Refactor `sso_clients`
Update the client table and model so it no longer treats JSON fields as the main storage.

Review and refactor:
- remove or deprecate `redirect_uris` JSON usage
- remove or deprecate `scopes` JSON usage
- remove or deprecate `client_secret_hash` direct column usage

IMPORTANT:
Do this safely with a migration strategy.
Do not break existing data.

If needed:
- add transitional migration
- backfill normalized tables from old JSON / old client secret hash
- keep old columns temporarily
- switch code to new normalized source of truth
- optionally keep old columns only for rollback compatibility if justified

Prefer a safe migration path over a destructive shortcut.

---

# REQUIRED BEHAVIORAL CHANGES

## Client create
When creating a client:
- create the client record
- create redirect URI rows
- attach scopes through pivot
- generate one initial plain secret
- store only secret hash
- return the plain secret once in a safe way for the admin UI
- log audit event for client created
- log audit event for client secret created

## Client update
When updating a client:
- update client core fields
- sync redirect URIs
- sync scopes
- do NOT reveal old secrets
- do NOT overwrite secrets silently
- preserve existing secrets unless explicit secret rotation action is performed

## Secret rotation
Implement a dedicated rotation flow.

Requirements:
- create a new secret row with new hash
- return the new plain secret once
- old secret should either:
  - be revoked immediately, or
  - be deactivated depending on current project design
Choose one approach and document it in code comments / notes.
Prefer secure behavior.
- add audit log event

## Secret revocation
Implement secret revocation capability.

Requirements:
- revoke a specific secret safely
- prevent revoking the last usable secret if that would leave an active client in an invalid state, unless explicitly intended by business rules
- add audit log event

---

# VALIDATION RULES

Use FormRequests.

## Client create/update validation
Validate at minimum:
- name
- identifier / client_id according to current project naming
- description if present
- token_policy_id if used
- is_active if present
- redirect_uris as array of strings
- scopes as array of existing scope IDs or codes based on current project design

Redirect URI validation:
- required array with at least one entry on create
- each URI must be valid
- trim input
- reject duplicates within the same payload
- later exact-match friendly
- no wildcard shortcuts
- no weak matching logic

Scopes validation:
- must exist
- must be unique
- sync cleanly

Secret actions:
- separate validation if secret rotation/revocation gets dedicated endpoints

---

# BACKEND IMPLEMENTATION TASKS

Implement or refactor the following as needed:

## Database / Migrations
Create migrations for:
- `redirect_uris`
- `client_scopes`
- `client_secrets`

Also create a migration/backfill strategy from old storage:
- migrate JSON redirect URIs to rows
- migrate JSON scopes to pivot
- migrate existing `client_secret_hash` into `client_secrets`
- ensure no data loss
- prefer idempotent-safe approach where reasonable

## Models
Refactor / add:
- `SsoClient`
- `RedirectUri`
- `ClientSecret`
- existing `Scope`
- existing `TokenPolicy`

Relationships should be explicit and clean.

## Repositories
Refactor client repository queries so list/detail pages can still work efficiently.
Add any dedicated repository methods needed for:
- fetching with redirect URIs
- fetching with scopes
- fetching active secrets safely
- admin index pagination/search/sort compatibility

## Services
Create or refactor service logic for:
- client create
- client update
- redirect URI sync
- scope sync
- secret generation
- secret rotation
- secret revocation

Use transactions where needed.

## Controllers
Keep controllers thin.
Move business logic into Services.
Support both Inertia admin pages and API payload consistency according to project conventions.

## Policies
Review and extend policies for:
- view clients
- create clients
- update clients
- rotate secrets
- revoke secrets
- view secret-related metadata safely

## Resources / DTO / Response shape
Keep response structure consistent with project rules.
Do not leak secret hashes.
Only include one-time plain secret when explicitly created/rotated.

## Audit logging
Log at minimum:
- client created
- client updated
- client secret created
- client secret rotated
- client secret revoked
- redirect URIs changed
- scopes changed

Use existing activity log patterns where possible.

---

# FRONTEND IMPLEMENTATION TASKS

Refactor the SSO Clients admin UI to work with normalized storage.

## Required UI changes
- Redirect URIs must be edited as a repeatable list, not as opaque JSON
- Scopes must remain clear and manageable
- Secret must be shown only once after create/rotate
- Existing secrets must not be re-readable
- Add explicit rotate secret action
- Add explicit revoke secret action if appropriate in current UI scope

## Pages/components
Refactor current SSO Clients pages/components as needed:
- Index page
- Create/Edit dialog or pages
- any supporting form partials
- any services used by frontend

Use:
- PrimeVue components only
- safe confirmations for destructive actions
- Toast for success/error
- DataTable stays usable
- no giant monolithic component

## UX expectations
- create/update remains smooth
- validation errors render clearly
- one-time secret display is obvious and safe
- redirect URI management is admin-friendly
- scope selection stays consistent with current project UX direction

---

# TESTING REQUIREMENTS

Add or update tests.

## Backend Feature Tests
Cover at minimum:
1. client create with normalized redirect URIs and scopes
2. client update syncs redirect URIs correctly
3. client update syncs scopes correctly
4. initial secret is stored hashed and plain secret is only exposed once
5. existing legacy data is migrated/backfilled correctly
6. secret rotation creates a new secret and does not expose old secrets
7. secret revocation works correctly
8. unauthorized users cannot manage secret actions
9. invalid redirect URIs are rejected
10. duplicate redirect URIs are rejected
11. duplicate scopes in payload are rejected
12. audit log entries are written for critical actions

## Frontend Tests
If there are existing vitest patterns, extend them for:
- create client form with multiple redirect URIs
- scope selection handling
- one-time secret display behavior
- rotate secret action flow
- validation error rendering

Follow existing project testing style.
Do not introduce a completely different testing approach.

---

# ACCEPTANCE CRITERIA

The task is complete only if all of the following are true:

1. `redirect_uris`, `client_scopes`, and `client_secrets` are the new normalized storage
2. client create/update works against normalized storage
3. old JSON/direct-secret data is safely migrated or backfilled
4. no plain secret is stored in DB
5. plain secret is shown only once after create/rotate
6. scopes are managed via pivot, not JSON source of truth
7. redirect URIs are managed via rows, not JSON source of truth
8. secret rotation is explicit and auditable
9. secret revocation is explicit and auditable
10. controllers remain thin
11. services contain the business logic
12. policies guard sensitive actions
13. tests cover the critical flows
14. code is production-ready and readable

---

# DELIVERABLE FORMAT

Work directly in the codebase.

At the end provide a Hungarian summary with these sections:

1. `Elkészült fájlok`
2. `Módosított fájlok`
3. `Adatbázis változások`
4. `Migráció / backfill stratégia`
5. `Backend logika`
6. `Frontend változások`
7. `Tesztek`
8. `Nyitott kérdések vagy javasolt következő lépések`

If you must choose between a quick hack and a secure maintainable solution, choose the secure maintainable solution.