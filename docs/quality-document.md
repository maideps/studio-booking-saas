# Quality Document

Rate each module on a scale of A (excellent) / B (good) / C (acceptable) / D (needs work) / F (failing).

## Modules

| Module | Test Coverage | Type Safety | Documentation | Arch Compliance | Performance |
|---|---|---|---|---|---|
| harness scaffold | B | n/a | B | B | n/a |
| web (Laravel + Vue) skeleton | B | A | B | B | n/a |
| tools/ launchers and Docker stack | C | n/a | B | B | n/a |
| web tenancy (`app/Tenancy`, `Tenant`, subdomain middleware) | A | A | A | A | B |
| web auth and roles (`app/Auth`, `Policies`, `Enums/Role`, auth controllers and pages) | A | A | A | A | B |
| web studios, staff and classes (settings, invitations, class templates and sessions) | A | A | A | A | B |
| web bookings (`BookSession`, `Booking`, PostgreSQL concurrency tests) | A | A | A | A | B |

## Summary

- **Overall Quality Grade:** B (tenancy, auth, roles, staff, classes and race-safe bookings in place; no cancellation, payments or notifications yet)
- **Blockers:** none
- **Areas requiring attention:**
  - Most PHP tests run on SQLite; only the `postgres` group (3 tests) runs on PostgreSQL. Every migration runs on PostgreSQL in that group (each test migrates a fresh database) and in `npm run smoke`, but the rest of the behaviour is tested on SQLite only.
  - The scope-bypass rule is a token scan of our own code; Laravel internals that skip global scopes (validation `unique`/`exists`, queue model restoration) are not covered by it (see `docs/ARCHITECTURE.md`, Tenancy).
  - The auth Vue pages have component tests for the login and members pages only. The register and password pages are covered through the Pest tests of their routes, not as components. The full UI is feat-012.
  - Bookings performance is graded B: bookings for one session queue on its row lock, and the schedule counts active bookings per session with one grouped subquery. Not measured under load.
  - Studios and classes performance is graded B: the schedule loads every upcoming session with its template and instructor (eager-loaded) and has no pagination or date window yet. Not measured.
  - The new Vue pages have component tests for the Join and Members pages only; the Settings, Classes and Schedule pages are covered through the Pest tests of their routes. The full UI is feat-012.
  - Auth performance is graded B: password hashing dominates login, and the members list loads every member of the studio without pagination. Not measured.
  - Tenancy performance is graded B: each scoped query adds one indexed `tenant_id` condition, and each tenant-aware job does one extra lookup of its tenant. Not measured.
  - The launchers and the Compose stack are tested only by running them (`npm run check`, `npm run smoke`); they have no unit tests of their own.

## Notes

- Session 001 created the harness scaffold, the project docs, the standards and the 14-feature plan.
- Session 002 (feat-001) added the Laravel 13 app in `web/` with its tests:
  - Pest: the health route, the Inertia welcome page, and architecture rules for strict types and leftover debug calls.
  - Vitest: the welcome page.
  - Type checks: Larastan level 8 and vue-tsc in strict mode.
  - Formatting: Pint, with `declare(strict_types=1)` enforced.
- Session 003 (feat-002) added the tenancy foundation. Pest now has 47 tests:
  - isolation of tenant A from tenant B in queries, relations, bulk updates, routes (with route model binding) and queued jobs, including one run through a real `database` queue worker;
  - write guards and fail-closed behaviour;
  - architecture rules for the trait, `tenant_id` indexes and foreign keys, tenant-aware jobs, scope bypasses and middleware order, each rule also fed a violation.
  - Each mechanism was also broken on purpose once (trait removed, bypass added, middleware priority removed, job middleware removed), and the matching tests failed.
- Session 004 (feat-003) added authentication and roles. Pest now has 116 tests and Vitest 6:
  - for every protected action (dashboard, log out, members list, role change): each role, a guest and a tenant A session cookie replayed on tenant B, plus `actingAs` a user of another tenant for the two actions behind a policy;
  - login: the same email in two tenants, credentials of one tenant on the other, remember-me cookies, the per-tenant lockout, session regeneration, and 404 off a tenant subdomain;
  - registration as customer only, with per-tenant email uniqueness;
  - per-tenant password reset tokens, links, throttling, expiry and the cross-tenant purge;
  - architecture rules for `unique`/`exists` validation rules and for auth routes outside the `tenant` middleware, each fed a violation.
  - Fourteen safeguards were each broken on purpose once (the `Gate::before` check, the token repository, the throttle key, the tenant-aware rule, the two halves of the test request reset, the tenant route group, three policy rules, the registration role, the token lookup, the reset link and the tenant check on the shared user prop). A test failed every time.
- Session 005 (feat-004) added studios, staff and classes. Pest now has 262 tests and Vitest 10:
  - a role matrix: every protected action (18 routes) for each role, plus a guest, a replayed session of another studio and `actingAs` an owner of another studio;
  - studio settings that only ever change the current studio;
  - invitations: the link host, hashed tokens, replacement, refusals, and acceptance with a new or an existing account;
  - class templates and sessions: per-studio name uniqueness, 404 for another studio's records, validation of template, instructor and time, timezone conversion, and composite foreign keys that refuse a cross-studio reference in the database;
  - a new architecture rule for foreign keys between tenant-owned tables, fed a violation.
  - Twenty safeguards were each broken on purpose once by a script, and a test failed every time.
- Session 006 (feat-005) added bookings and the PostgreSQL test database. Pest now has 281 tests on SQLite plus 3 on PostgreSQL, and Vitest 13:
  - parallel booking on PostgreSQL in separate processes: eight customers race for the last place (exactly one booking), and one customer's five identical requests (exactly one booking). With the row lock removed, all eight got in, and the test failed;
  - the booking rules on SQLite: full, started and already booked are refused; released bookings neither count nor block; another studio's session is a 404; capacity cannot drop below bookings; database-level refusals;
  - the booking route in the role matrix.
  - Eleven safeguards were each broken on purpose once (the row lock on PostgreSQL, ten on SQLite), and a test failed every time.

---

_Update this document at session close for modules touched in the session._
