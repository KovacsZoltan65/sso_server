You are working on a Laravel 13 + Vue 3 + Vite + Inertia + PrimeVue project.

Task:
Perform a full backend + frontend test audit and remediation pass for the project.

IMPORTANT:
This is not a read-only audit.
You must inspect, install missing test dependencies, add missing tests, run the test suites, fix failing tests, and update project instructions so testing becomes a mandatory part of future implementation work.

Frontend testing must use:

- Vitest

Backend testing must use the project’s existing Laravel/PHP testing stack.

GOAL:
Bring the project into a state where:

- required backend test packages are installed
- required frontend test packages are installed
- backend tests are reviewed and missing critical tests are added
- frontend tests are reviewed and missing critical tests are added
- tests are executed
- failing tests are fixed
- AGENTS.md explicitly enforces test creation and test maintenance for future work

---

## PRIMARY OBJECTIVES

You must complete all of the following:

1. Verify whether backend and frontend test packages are installed
2. Install missing test packages
3. Audit backend tests
4. Add missing backend tests
5. Run backend tests
6. Fix backend test failures
7. Audit frontend tests
8. Add missing frontend tests
9. Run frontend tests
10. Fix frontend test failures
11. Review `AGENTS.md`
12. Add or improve testing rules in `AGENTS.md` so required tests are always created and maintained in future tasks

This is a test hardening and enforcement task, not just a diagnostics report.

---

## STACK / TEST EXPECTATIONS

Backend:

- Laravel 13
- PHP 8.4+
- use the project’s existing PHP test stack
- if Pest is already installed, continue using Pest
- if PHPUnit config exists, keep compatibility
- do not replace the project’s test style without reason

Frontend:

- Vue 3
- Vite
- Inertia
- PrimeVue
- Vitest for frontend tests

IMPORTANT:
Use the existing project conventions first.
Do not introduce unnecessary alternative test frameworks.

---

## STEP 1 — VERIFY TEST PACKAGES

Inspect the project and verify whether the required testing packages and configs are present.

Backend:

- inspect `composer.json`
- inspect test-related config files
- determine whether Pest / PHPUnit and required helpers are installed

Frontend:

- inspect `package.json`
- inspect `vitest.config.*` or Vite/Vitest setup
- determine whether Vitest and required Vue test packages are installed

At minimum verify presence/need for things like:

- vitest
- jsdom
- @vue/test-utils
- any necessary Vite/Vitest Vue plugin compatibility
- any test setup file used by current project pattern

Do not assume they are installed.
Check first.

---

## STEP 2 — INSTALL MISSING PACKAGES

If backend or frontend test packages are missing, install them.

Requirements:

- add only what is actually needed
- keep versions compatible with the existing stack
- avoid random testing libraries
- prefer the minimal clean test setup that supports the current project

Frontend target:

- Vitest-based Vue component/unit testing
- enough setup to test Vue 3 components cleanly

After installation:

- ensure scripts/config are correct
- do not leave half-configured tooling

---

## STEP 3 — BACKEND TEST AUDIT

Inspect the backend test suite and evaluate:

- existing coverage
- missing CRUD tests
- missing authorization tests
- missing validation tests
- missing feature tests for critical flows
- broken/outdated tests
- duplicated or low-value tests

Pay special attention to:

- Users
- Roles
- Permissions
- authentication / authorization related flows
- any newly added modal-first backend flow implications
- policy checks
- store/update/delete behavior
- validation coverage
- Inertia response coverage where appropriate

You must identify important missing backend tests and add them.

At minimum, ensure critical backend flows are covered with feature tests.

---

## STEP 4 — ADD MISSING BACKEND TESTS

Create the missing backend tests needed to bring the project closer to production-ready coverage.

Focus on:

- CRUD happy paths
- validation failures
- authorization failures
- protected delete rules if present
- correct redirect / flash behavior
- Inertia response structure where relevant
- modal-first index payload expectations for Roles/Permissions if those modules use that pattern

Do not spam meaningless tests.
Add targeted, high-value tests.

Mirror existing project test style:

- if Pest is used, write Pest tests
- if PHPUnit class-style is the established convention, follow it
- stay consistent with current structure and naming

---

## STEP 5 — RUN BACKEND TESTS

Run the backend tests.

Requirements:

- execute the appropriate test command(s)
- inspect failures carefully
- fix failing tests
- fix code if the tests reveal real implementation bugs
- do not silence or delete failing tests just to get green output

If fixtures, factories, policies, routes, or setup are broken, repair them properly.

---

## STEP 6 — FRONTEND TEST AUDIT

Inspect the frontend test suite and evaluate:

- existing Vitest setup
- missing component/page tests
- broken or outdated tests
- missing coverage for critical interaction flows
- missing tests for modal CRUD behavior
- missing tests for table action flows
- missing tests for form submission behavior
- missing tests for validation/error rendering where appropriate

Pay special attention to:

- Users
- Roles
- Permissions
- modal-based CRUD flows
- DataTable-driven pages
- CreateModal/EditModal behavior
- shared form field partials
- Toast / ConfirmDialog interaction patterns if they are mocked in project tests
- Inertia form submission behavior if locally testable
- component contracts and emits

You must identify important missing frontend tests and add them.

---

## STEP 7 — ADD MISSING FRONTEND TESTS

Add missing frontend tests using Vitest.

Use the existing frontend testing style if present.

Typical targets:

- page renders correctly
- create button opens modal
- edit action opens modal with selected data
- shared form fields render expected inputs
- submit actions trigger expected calls/emits
- modal close/reset behavior
- delete confirmation flow handling if testable in current setup
- success callbacks refresh or emit expected signals
- validation/error display behavior where practical

If the project already uses mocks/stubs for:

- PrimeVue components
- Inertia router
- Toast
- ConfirmDialog
- custom services/helpers

then follow that same test strategy.

Do not overcomplicate UI tests.
Prefer stable, maintainable tests.

---

## STEP 8 — RUN FRONTEND TESTS

Run the frontend Vitest suite.

Requirements:

- use the proper frontend test command
- inspect failures carefully
- fix the failing tests
- fix real component/setup issues if tests reveal implementation bugs
- do not delete valuable tests to avoid failures

Ensure the Vitest environment is actually usable after your changes.

---

## STEP 9 — REVIEW AND UPDATE AGENTS.md

Inspect `AGENTS.md` carefully.

Determine whether it already includes clear requirements for:

- backend tests
- frontend tests
- when tests must be added
- what level of coverage is expected
- running tests after implementation
- fixing broken tests rather than ignoring them
- test updates when behavior changes

If the file is missing testing expectations or the rules are too weak, update it.

---

## STEP 10 — STRENGTHEN AGENTS.md TEST RULES

Update `AGENTS.md` so future implementation work consistently includes testing.

The testing rules added or improved must clearly enforce that:

- critical backend flows must have tests
- frontend interactive behavior must have Vitest tests where appropriate
- CRUD modules require tests for happy path + validation + authorization
- changed behavior requires corresponding test updates
- new modules should not be considered complete without tests
- failing tests must be fixed, not ignored
- backend and frontend test suites should be run after meaningful feature work
- security-sensitive flows require explicit test coverage

Make the AGENTS.md guidance strict, practical, and aligned with this project.

Do not add vague fluff.
Add enforceable engineering rules.

---

## IMPLEMENTATION STYLE RULES

- Follow existing project conventions first
- Prefer consistency over novelty
- Keep tests readable and maintainable
- Do not introduce random abstractions
- Do not replace working test infrastructure without reason
- Do not delete useful tests unless they are genuinely obsolete and replaced properly
- Fix root causes where possible
- Keep code and test changes review-friendly

---

## IMPORTANT QUALITY RULES

- Do not treat this as a report-only audit
- You must actually modify the codebase where needed
- You must actually install missing dependencies where needed
- You must actually run tests
- You must actually fix failures
- You must actually add missing tests
- You must actually update AGENTS.md

Do not stop after listing problems.

---

## EXPECTED BACKEND TEST AREAS

At minimum evaluate and cover, where relevant:

- index access
- create/store
- edit/update
- destroy/delete
- validation errors
- authorization failures
- redirect/flash behavior
- policy enforcement
- Roles / Permissions relation-sensitive behavior if implemented
- modal-first Inertia payload response expectations if the backend uses that pattern

---

## EXPECTED FRONTEND TEST AREAS

At minimum evaluate and cover, where relevant:

- index rendering
- modal open/close
- create/edit modal props and emits
- shared form fields rendering
- submit button disabled while processing
- selected entity loading into edit modal
- table row action behavior
- delete confirmation handling
- component event contracts
- integration with mocked router/services/helpers where current project style uses them

Frontend tests must use Vitest.

---

## COMMAND / EXECUTION EXPECTATION

You must inspect available project scripts and run the correct commands for:

- backend tests
- frontend tests

Do not guess blindly if the scripts/config show the intended command.

---

## OUTPUT REQUIREMENTS

Provide:

1. Full file contents for all created/updated files
2. Clear file paths for each modified file
3. A short audit summary of what was missing
4. A list of installed packages, if any were added
5. A list of backend tests added or updated
6. A list of frontend tests added or updated
7. The commands used to run backend and frontend tests
8. A summary of failures found and how they were fixed
9. The updated `AGENTS.md` content or diff-worthy replacement section
10. Final status of backend and frontend test results

IMPORTANT:
Output real code and real file changes.
Do not output pseudo-code.
Do not stop at recommendations.
Complete the implementation and remediation work end-to-end.
