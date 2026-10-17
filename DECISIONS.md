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
