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
