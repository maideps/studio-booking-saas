# Architectural Decisions Log

This file records significant architectural decisions, their context, alternatives considered, and consequences. It serves as durable cross-session memory for why the codebase is structured the way it is.

## Decision Template

```
### D-XXX: [Decision Title]

**Date:** YYYY-MM-DD
**Status:** proposed | accepted | deprecated | superseded by D-YYY
**Context:** [What problem are we solving? What constraints exist?]
**Decision:** [What did we decide to do?]
**Alternatives considered:**
- Alternative 1: [description] — Rejected because [reason]
**Consequences:**
- Positive: [benefits gained]
- Negative: [tradeoffs accepted]
```

## Recorded Decisions

<!-- Add decision records here, newest last. Expand long records into docs/decisions/ and link back from here. -->

### D-001: Scope and stack for the portfolio project

**Date:** 2026-10-17
**Status:** accepted
**Context:** The goal is a solid full-stack portfolio piece built with AI coding agents under a controlled process, in the author's strongest stack (Laravel, Vue), different from the first project (Python and React). No employer code or data may be used.
**Decision:** A multi-tenant booking and membership SaaS for small studios: Laravel (PHP 8.3+), Vue 3 + TypeScript via Inertia, PostgreSQL, Redis, Stripe in test mode (Cashier), Pest, Larastan, Pint, Playwright, Docker Compose. Synthetic data only.
**Alternatives considered:**
- A helpdesk or property-management domain — Rejected: same engineering, but booking has a sharper concurrency problem (capacity) that is easy to demonstrate.
- Separate Vue SPA over a pure API — Rejected for the main UI: Inertia ships faster and matches common Laravel shops; a small public JSON API is still built (feat-011) to show API design.
**Consequences:**
- Positive: demonstrates tenancy, concurrency, billing, roles and queues in one product.
- Negative: Stripe and multi-tenancy add scope; scope limits are written in `docs/PRODUCT.md` (non-goals).

### D-002: Tenancy model is one database with a tenant_id column and a global scope

**Date:** 2026-10-17
**Status:** accepted
**Context:** Studios share one system and must never see each other's data. The common options are a shared database with a tenant column, a schema or database per tenant, or a package that automates one of these.
**Decision:** One PostgreSQL database; every tenant-owned table (including users) has an indexed `tenant_id`; a `BelongsToTenant` trait adds a global scope and fills `tenant_id` on create; the tenant is resolved from the subdomain and passed explicitly into queued jobs; email is unique per tenant. Bypassing the scope goes through one named helper that an architecture test restricts.
**Alternatives considered:**
- Database or schema per tenant — Rejected: stronger isolation but much heavier migrations and operations for a portfolio-sized app.
- A tenancy package — Rejected: hides the mechanism the project exists to demonstrate.
- PostgreSQL row-level security — Not now; may be added later as defense in depth once the application-level scope is proven by tests.
**Consequences:**
- Positive: simple operations; the isolation guarantee is explicit and testable.
- Negative: a missed trait or a raw query can leak data, so the isolation tests and the architecture test are mandatory gates.

### D-003: The Laravel application lives in web/, not at the repository root

**Date:** 2026-10-17
**Status:** accepted
**Context:** The harness owns the root `package.json`, `scripts/` and `tests/`. A Laravel project also wants a root `package.json`, `app/` and `tests/`.
**Decision:** Create the Laravel application in `web/`. Small launchers in `tools/` run PHP and Node tooling from the harness `lint`, `typecheck`, `test` and `build` scripts (the same pattern used in ai-invoice-intake).
**Alternatives considered:**
- Laravel at the root — Rejected: collides with harness files and blurs what is framework and what is product.
**Consequences:**
- Positive: a clean seam between harness and product; upgrades never touch product code.
- Negative: paths are one level deeper and the launchers must work on Windows and Linux.

### D-004: Stripe and mail are always faked in tests

**Date:** 2026-10-17
**Status:** accepted
**Context:** Tests must be deterministic and must never touch the network or a real account.
**Decision:** Stripe is used through an interface owned by the domain; tests use a fake gateway and recorded webhook fixtures. Mail uses Laravel's fakes in tests and Mailpit locally.
**Alternatives considered:**
- Stripe's test API in CI — Rejected: slow, flaky and needs secrets in CI.
**Consequences:**
- Positive: fast, offline, reproducible tests.
- Negative: fixtures can drift from Stripe's real payloads; refresh them by hand when upgrading the SDK.

### D-005: PHP tests run on SQLite in memory until PostgreSQL behaviour is under test

**Date:** 2026-10-19
**Status:** accepted
**Context:** D-001 and D-002 put the product on PostgreSQL. Laravel's `phpunit.xml` defaults to SQLite in memory, which lets `npm run test` run with no containers, on any machine and in CI. feat-005 will test capacity with row locks and parallel requests, and SQLite cannot show that behaviour.
**Decision:** Keep SQLite in memory for the PHP test suite through feat-004. Before feat-005, the suite (or the tests that depend on PostgreSQL) moves to a PostgreSQL test database, started by the test tooling. The Compose stack always uses PostgreSQL.
**Alternatives considered:**
- PostgreSQL for every test from feat-001 — Rejected for now: every `npm run test` would need Docker before any test relies on PostgreSQL.
**Consequences:**
- Positive: fast, dependency-free tests while the schema is small.
- Negative: SQLite and PostgreSQL differ (types, locking, JSON). Tenancy tests in feat-002 to feat-004 must not depend on PostgreSQL-only behaviour, and the switch is a required step of feat-005.

### D-006: One app image for the web app, worker and scheduler; APP_KEY generated at runtime

**Date:** 2026-10-19
**Status:** accepted
**Context:** `docker compose up` must start the whole system with one command (`docs/PRODUCT.md`), and secrets must never be committed. Laravel needs the same `APP_KEY` in every process that reads encrypted data.
**Decision:** `docker/app/Dockerfile` builds one image (Vite assets, then PHP 8.3 with `pdo_pgsql` and `redis`, running as `www-data`), and Compose runs it three times with different commands.
- When `APP_KEY` is not set, the entrypoint generates a key into the shared `app-secrets` volume.
- The worker and scheduler start only after the app is healthy, so they read the same key and never write it.
- The app runs migrations on start. Published ports are bound to `127.0.0.1`.
**Alternatives considered:**
- A committed development key in `docker-compose.yml` — Rejected: it is still a committed secret.
- A required `.env` at the root — Rejected: it breaks the one-command start.
**Consequences:**
- Positive: one command, no committed secret, and the three roles always run the same code.
- Negative: removing the `app-secrets` volume rotates the key and invalidates sessions. Code changes need `docker compose up --build`.

### D-007: The tenant scope fails closed and tenant writes are guarded

**Date:** 2026-10-19
**Status:** accepted
**Context:** D-002 sets the tenancy model but not what happens when code runs without a tenant (a console command, a queued job that lost its tenant, a route without the `tenant` middleware), or when a record is written for the wrong tenant. A scope that silently does nothing in those cases returns every tenant's rows.
**Decision:**
- With no current tenant, a query on a tenant-owned model throws `TenancyViolation` instead of running unscoped.
- `BelongsToTenant` also guards writes: creates, updates and deletes need a tenant context that matches the record's `tenant_id`, and `tenant_id` can never change. Bulk updates skip model events, so tenant-owned models use a `TenantBuilder` that refuses a bulk write of `tenant_id`.
- The `tenant` middleware is sorted before every other middleware, so the session save, authentication and route model binding run inside the tenant.
- The current tenant is only set through `$tenant->run()`, which always restores the previous tenant.
- Queued jobs carry the tenant id as a serialized property, captured when the job is created. A job middleware runs the job inside that tenant.
- The architecture test treats any code that drops global scopes, skips model events, bulk-inserts or reaches the database outside Eloquent as a scope bypass, unless it is in `CrossTenant`. Every queued job must be tenant-aware unless it is listed as central.
- `users` gets `tenant_id` and a per-tenant unique email now, through a new migration, so the trait and the architecture test cover every model from the start. Login and registration stay in feat-003.
**Alternatives considered:**
- An empty result set with no tenant — Rejected: it hides the bug as "no data" instead of failing loudly.
- Setting the tenant on the worker automatically from a queue payload hook — Rejected: D-002 asks for the tenant to be passed explicitly, and an implicit hook is easy to miss when reading a job.
- Leaving `users` unscoped until feat-003 — Rejected: the architecture test would need an exception for it.
**Consequences:**
- Positive: a missing or wrong tenant is a loud error in tests, not a silent leak.
- Negative: console commands, seeders and scheduled jobs must enter a tenant (`$tenant->run()`) or use `CrossTenant`. Seeders cannot use `WithoutModelEvents`, and bulk inserts on tenant tables need a reviewed exception to the rule.

### D-008: Authentication runs inside the tenant, and authorization starts with a tenant check

**Date:** 2026-10-19
**Status:** accepted
**Context:** feat-003 adds login, registration, password reset and roles. Each tenant has its own users, and the same email may exist in two tenants (D-002). Three parts of Laravel's auth stack do not go through the tenant scope:
- the password reset token table is keyed by email alone;
- the `unique` and `exists` validation rules query without global scopes;
- the login rate limiter's cache keys are not tenant-prefixed (D-007).
Policies also need a rule for a user who is signed in but belongs to another tenant.
**Decision:**
- **Auth routes.** Login, registration, password reset and every route that needs a signed-in user run behind the `tenant` middleware. The Eloquent user provider then queries through the tenant scope, so credentials, the session's user id and the remember-me token only resolve to a user of the current tenant. An architecture rule fails on any route with `auth`, `guest` or `can` middleware outside the `tenant` middleware.
- **Roles.** Every user has one role (`App\Enums\Role`: owner, staff, customer), stored on `users.role`. Registration always creates a customer, and `role` is not mass-assignable.
- **Policies.** A `Gate::before` check denies every ability to a user whose `tenant_id` is not the current tenant, and to everyone when there is no tenant. Policies then only decide by role.
- **Password reset tokens.** `password_reset_tokens` becomes a tenant-owned table with a `PasswordResetToken` model and a unique (`tenant_id`, `email`). `TenantTokenRepository` reads and writes it through the model, so tokens get the tenant scope and write guards. `TenantPasswordBrokerManager` installs the repository. Expired tokens are purged across tenants through `CrossTenant`. Reset links are built from the user's own tenant (`Tenant::url()`), not from the request.
- **Validation rules.** `unique` and `exists` rules on tenant-owned tables go through `TenantRule`, which adds `tenant_id`. An architecture rule fails on any other `Rule::unique`, `Rule::exists`, `new Unique`, `new Exists` or `unique:` / `exists:` rule string.
- **Login throttle.** The login throttle key includes the tenant id.
- **Session driver in tests.** The test suite uses the `database` session driver, the app's default, so every login test saves the session through the database handler inside the tenant.
**Alternatives considered:**
- Laravel's `cache` token driver — Rejected: its cache keys are not tenant-prefixed either.
- A tenant check inside each policy method — Rejected: easy to forget in a new policy. `Gate::before` covers every ability by default.
- Rejecting a cross-tenant user in a middleware on every authenticated route — Not now. The guard can never return such a user, because its lookups are scoped (the session-replay tests show this). Every action that touches tenant data goes through a policy, and `Gate::before` covers those.
- Building login and registration with Breeze or Fortify — Rejected: they hide the tenant-scoping points this project exists to show, and their published code would still need the same changes.
**Consequences:**
- Positive: a session, remember-me cookie, reset token or rate-limit lockout from one tenant has no effect in another, and tests prove each case. New policies get the tenant check for free.
- Negative: `TenantPasswordBrokerManager` overrides a protected Laravel method, so a Laravel upgrade could break it (the password reset tests would fail). The broker's `table`, `connection` and `driver` options no longer apply. Central tables, such as tenant slugs at signup, will need their own reviewed exception to the validation-rule check.
