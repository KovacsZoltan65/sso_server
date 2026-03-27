# SSO Server

Security-critical Laravel application for central authentication, authorization, OAuth-style token flows, client management, scopes, and token policy administration.

## Local Setup

1. Copy [`.env.example`](/c:/wamp64/www/sso/sso_server/.env.example) to `.env`.
2. Fill in local-only values for `APP_KEY`, database access, mail, cache, queue, and any third-party credentials.
3. Generate an application key if needed:

```bash
php artisan key:generate
```

For automated tests, use [`.env.testing.example`](/c:/wamp64/www/sso/sso_server/.env.testing.example) as the template for a local `.env.testing` only if your test workflow needs a dedicated file. The default PHPUnit setup already provides an in-memory SQLite configuration.

## Environment File Security

- Never commit `.env` or any `.env.*` file containing real values.
- Treat `.env`, `.env.local`, `.env.staging`, `.env.production`, and `.env.testing` as local or deployment-managed secrets.
- Only commit example templates such as [`.env.example`](/c:/wamp64/www/sso/sso_server/.env.example) and [`.env.testing.example`](/c:/wamp64/www/sso/sso_server/.env.testing.example).
- Never include environment files in ad-hoc zip, backup, or export packages.
- Rotate secrets immediately if a real environment file was ever shared or exported.

## Multi-Environment Practice

- Local development: keep secrets only in your untracked `.env`.
- Testing: prefer PHPUnit-provided environment values or an untracked local `.env.testing`.
- Staging and production: inject secrets through the target environment or deployment system, not through committed files.
- Keep example files non-sensitive and use placeholder values only.

## Security Regression Suite

The project maintains a focused backend security regression gate for the most critical auth and access-control guarantees:

- OAuth abuse resistance and rate limiting
- admin authorization and IDOR abuse protection
- audit log access control
- profile vs admin boundary protection

Run it locally with:

```bash
composer test:security
```

The same command is executed in CI by [`.github/workflows/security-regression.yml`](/c:/wamp64/www/sso/sso_server/.github/workflows/security-regression.yml). A pull request or push fails if any test in the Pest `security` group fails.

When adding a new security-critical test, include it in the Pest `security` group in [`tests/Pest.php`](/c:/wamp64/www/sso/sso_server/tests/Pest.php). New auth, authorization, token, audit-log, or privilege-boundary behavior changes should add or update at least one security regression test.

## Integration Contract

Server-client OAuth/SSO integration contract is defined in:

- [`docs/integration-contract.md`](/c:/wamp64/www/sso/sso_server/docs/integration-contract.md)
