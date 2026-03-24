# 🔥 SSO SERVER — AI DEV MODE (SECURE AUTH CORE)

You are working on the **SSO_SERVER** project.

This is a **security-critical authentication and authorization server**.

Your job is to generate **production-ready, secure, and maintainable code**.

---

# 🚨 PRIORITY ORDER (STRICT)

When generating code, ALWAYS follow this order:

1. 🔐 Security
2. 🧠 Correctness
3. 🧱 Architecture consistency
4. 🔍 Auditability
5. 🔄 Extensibility (OAuth2 / OIDC ready)
6. 🧹 Clean code

If ANY conflict appears → STOP and ask.

---

# 🧠 CORE MENTAL MODEL

This system is:

- NOT a simple CRUD app
- NOT a demo auth system
- NOT a shortcut implementation

This is:

✅ Central SSO authority  
✅ Token issuer  
✅ Trust boundary  
✅ Security gateway

---

# 🧱 ARCHITECTURE (NON-NEGOTIABLE)

Backend must follow:

Controller → Service → Repository

### NEVER BREAK THIS

## Controllers

- Thin only
- Call FormRequest
- Call Service
- Return response

❌ NO business logic  
❌ NO complex branching  
❌ NO queries

---

## Services

- Business logic
- Security rules
- Token flows
- Secret lifecycle
- Transactions

---

## Repositories

- Queries only
- Filtering
- Pagination
- Data access abstraction

❌ NO business logic  
❌ NO authorization

---

# 🧾 VALIDATION (MANDATORY)

- Always use FormRequest
- Never validate inline in Controller
- Validate EVERYTHING:
    - redirect URIs
    - scopes
    - TTL values
    - booleans
    - enums

---

# 🔐 SECURITY RULES (CRITICAL)

## Secrets

- NEVER store plain secrets
- ALWAYS hash before saving
- NEVER return hashed secrets
- Show secret ONLY once at creation

## Tokens

- Must support revocation
- Must support expiration
- Must support refresh flow
- Must be policy-driven (TTL, rotation)

## Redirect URIs

- STRICT MATCH ONLY
- NO wildcards (unless explicitly allowed)
- NO partial match

## Input

- Reject invalid scopes
- Reject inactive clients
- Reject invalid redirect URIs
- Reject malformed requests

## Logging

- Log ALL critical events:
    - login success/failure
    - token issue
    - token refresh
    - token revoke
    - secret creation/revocation

## NEVER

- log secrets
- expose tokens
- leak internal errors

---

# 🧩 DOMAIN EXPECTATIONS

You are building:

- SSO Clients
- Redirect URIs
- Scopes
- Client Secrets
- Token Policies
- Tokens
- Audit Logs

Design for future:

- OAuth2
- OpenID Connect
- PKCE
- multi-client ecosystem

---

# 🌐 API CONTRACT (STRICT)

ALL responses MUST follow:

```json
{
    "message": "",
    "data": {},
    "meta": {},
    "errors": {}
}
```

Status codes
200 OK
201 Created
204 No Content
401 Unauthorized
403 Forbidden
422 Validation
409 Conflict

❌ NO random formats
❌ NO mixed structures

🧪 TESTING (REQUIRED)

Always generate tests for critical flows:

authentication
token issuing
token refresh
token revocation
client CRUD
secret handling
redirect URI validation
policy enforcement

Test BOTH:

success cases
failure cases
🎨 FRONTEND RULES
Vue 3 Composition API

<script setup>
PrimeVue ONLY
UI
DataTable for lists
Dialog for CRUD
Toast required
ConfirmDialog required
UX SECURITY
secrets visible ONCE
audit logs READ-ONLY
destructive actions CONFIRMED
🧨 FORBIDDEN

DO NOT:

store plain secrets
expose secrets
skip validation
skip policies
use loose redirect matching
put logic in controllers
mix response formats
log tokens/secrets
🧠 IMPLEMENTATION STYLE
Prefer clarity over cleverness
Avoid over-engineering
Follow Laravel conventions
Keep code review-friendly
🧭 WHEN GENERATING CODE

You MUST:

Respect architecture
Respect security rules
Use FormRequest
Use Policies
Use Services
Use Repositories
Keep responses consistent
Add tests for critical parts
🛑 STOP CONDITIONS

STOP and ask if:

security decision unclear
redirect URI rule ambiguous
token flow undefined
data model unclear
conflicting requirements
📦 OUTPUT FORMAT (IMPORTANT)

When generating code:

Provide FULL files (not fragments)
Use clear file paths
Keep structure consistent
Avoid pseudo-code
🚀 MINDSET

You are not coding fast.

You are building:
→ a secure auth system
→ that others will trust
→ and depend on

Every mistake here = security risk.

Act accordingly.
