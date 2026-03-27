# SSO Server <-> SSO Client Integration Contract

## Scope
This document defines the explicit, test-backed integration contract between:
- `sso_server`
- `sso_client`

It describes the current production behavior only.

## 1. Authorize Request Contract
Client starts login by redirecting to:
- `GET {SSO_SERVER_BASE_URL}{SSO_AUTHORIZE_ENDPOINT}`
- Default: `GET /oauth/authorize`

Required query params sent by `sso_client`:
- `response_type=code`
- `client_id`
- `redirect_uri`
- `scope` (space-separated, configured via `SSO_SCOPES`)
- `state` (random 64 chars, stored in session)
- `code_challenge`
- `code_challenge_method=S256`

Server-side behavior:
- Valid request and authenticated user -> `302` redirect to `redirect_uri` with `code` and echoed `state`.
- Invalid client / redirect / scope -> current server behavior is validation failure on the authorize route (`302` with session validation errors on server side), not callback redirect.

## 2. Callback Contract (`sso_client`)
Client callback endpoint:
- `GET /auth/sso/callback`

Client expects:
- success path: `code` and `state`
- failure path: optional OAuth-style `error`

Validation rules in client:
- `code` missing -> fail
- `state` missing -> fail
- `state` mismatch / missing expected session state -> fail
- missing PKCE verifier in session -> fail
- `error` present in callback query -> fail

## 3. Token Response Contract
Token endpoint:
- `POST {SSO_SERVER_BASE_URL}{SSO_TOKEN_ENDPOINT}`
- Default: `POST /api/oauth/token`

Request grant used by client:
- `grant_type=authorization_code`
- `client_id`
- `client_secret` (if configured)
- `redirect_uri`
- `code`
- `code_verifier`

Successful response (authoritative format):
```json
{
  "message": "OAuth token issued successfully.",
  "data": {
    "token_type": "Bearer",
    "access_token": "...",
    "refresh_token": "...",
    "expires_in": 3600,
    "refresh_token_expires_in": 86400,
    "scope": "openid profile email"
  },
  "meta": {},
  "errors": {}
}
```

Contract rule:
- `sso_client` reads `data.access_token` only (no top-level fallback).

Failure response format:
```json
{
  "message": "OAuth token request failed.",
  "data": {},
  "meta": {},
  "errors": {
    "field": ["reason"]
  }
}
```

## 4. UserInfo Response Contract
Userinfo endpoint:
- `GET {SSO_SERVER_BASE_URL}{SSO_USERINFO_ENDPOINT}`
- Default: `GET /api/oauth/userinfo`
- Authorization: `Bearer {access_token}`

Successful response:
```json
{
  "message": "User info retrieved successfully.",
  "data": {
    "sub": "123",
    "name": "Example User",
    "email": "user@example.test",
    "email_verified": true
  },
  "meta": {},
  "errors": {}
}
```

Claims contract:
- Guaranteed: `data.sub`
- Optional by scope: `data.name`, `data.email`, `data.email_verified`

Client contract:
- Reads userinfo from `data` only (no top-level fallback).
- Requires `data.email` to build local user session.

## 5. Logout Contract
Current explicit contract:
- `sso_client` performs local logout only (`POST /auth/logout`).
- Session is fully cleared locally.
- No single logout handshake with server is active in current contract.

## 6. Self-Service Profile Contract
Profile endpoints:
- `GET /api/profile`
- `PATCH /api/profile`
- `PATCH /api/profile/password`

Authentication model:
- browser calls the server profile endpoints directly
- server session remains the authentication authority
- client sends credentialed requests and uses the explicit JSON envelope only

Editable fields:
- `name`

Read-only fields:
- `email`
- `emailVerifiedAt`

Rejected through self-service:
- `roles`
- `permissions`
- `email_verified_at`
- admin/status/security fields
- any unexpected field not on the explicit whitelist

Profile fetch success:
```json
{
  "message": "Profile retrieved successfully.",
  "data": {
    "id": 123,
    "name": "Example User",
    "email": "user@example.test",
    "emailVerifiedAt": "2026-03-27T15:00:00+00:00"
  },
  "meta": {
    "editable_fields": ["name"],
    "read_only_fields": ["email", "emailVerifiedAt"],
    "csrf_token": "..."
  },
  "errors": {}
}
```

Password update success:
```json
{
  "message": "Password updated successfully.",
  "data": {},
  "meta": {
    "editable_fields": ["name"],
    "read_only_fields": ["email", "emailVerifiedAt"],
    "csrf_token": "..."
  },
  "errors": {}
}
```

Validation failure format remains the standard envelope:
```json
{
  "message": "Validation failed.",
  "data": [],
  "meta": [],
  "errors": {
    "field": ["reason"]
  }
}
```

## 7. Session/Auth State Contract (Client)
Local authenticated state is created only after:
1. valid callback validation
2. successful token exchange
3. successful userinfo fetch with usable `email`
4. local user resolution by email
5. Laravel web login + session regeneration

Client becomes guest when:
- local logout is called, or
- protected route is accessed without valid session (`401` JSON for API-style request, redirect to login for browser).

## 8. Error Contract Matrix
| Case | Server status/body | Transport | Client handling |
|---|---|---|---|
| invalid client (authorize) | 302 + validation session errors (`client_id`) | redirect on server side | not callback-based, user remains on server flow |
| inactive client (authorize/token) | 302 validation (authorize) / 422 JSON (token) | redirect or JSON | token phase fails and client returns login error |
| redirect mismatch (authorize/token) | 302 validation (authorize) / 422 JSON (token) | redirect or JSON | token phase fails |
| disallowed scope (authorize) | 302 + validation session errors (`scope`) | redirect on server side | not callback-based |
| missing state (callback) | n/a (client-side callback validation) | query to client callback | client rejects |
| invalid state (callback) | n/a (client-side callback validation) | query to client callback | client rejects |
| missing code (callback) | n/a (client-side callback validation) | query to client callback | client rejects |
| invalid/expired/reused code (token) | 422 JSON envelope with `errors.code` | JSON | client rejects token exchange |
| token endpoint failure/network | n/a | transport failure | client rejects token exchange |
| userinfo unauthorized | 401 JSON envelope | JSON | client rejects userinfo phase |
| userinfo forbidden | 403 JSON envelope | JSON | client rejects userinfo phase |
| forbidden self-service profile field | 422 JSON envelope with per-field errors | JSON | client shows field/domain errors |
| unauthorized protected route (client app) | 302 to login (HTML) / 401 JSON (`reauth_to`) | redirect or JSON | explicit re-auth behavior |

## 9. Config Contract
Client configuration must define:
- `SSO_SERVER_BASE_URL`
- `SSO_AUTHORIZE_ENDPOINT`
- `SSO_TOKEN_ENDPOINT`
- `SSO_USERINFO_ENDPOINT`
- `SSO_CLIENT_ID`
- `SSO_CLIENT_SECRET` (when confidential client auth is required)
- `SSO_REDIRECT_URI`
- `SSO_SCOPES` (must include `email`, because client session mapping requires `userinfo.email`)

Server configuration/data must align:
- OAuth client exists with same `client_id`
- allowed `redirect_uri` includes `SSO_REDIRECT_URI` exactly
- allowed scopes include client-requested scopes
- token policy PKCE settings are compatible with client request
- `CORS_ALLOWED_ORIGINS` includes the exact `sso_client` browser origin for direct self-service profile calls

## 10. Contract Test Coverage
Server:
- `tests/Feature/OAuth/OAuthAuthorizationCodeFlowTest.php`
- `tests/Feature/OAuth/OAuthUserInfoTest.php`
- `tests/Feature/Api/SelfServiceProfileApiTest.php`

Client:
- `tests/Feature/Auth/SsoAuthenticationTest.php`
- `tests/Feature/ProfileTest.php`

These tests are the regression guard for this contract.
