# Sprint Contract

**Feature ID:** feat-002
**Feature Name:** Tenancy foundation
**Date:** 2026-10-19
**Estimated Duration:** 1 session

## What I Will Build

The tenancy mechanism from D-002, in `web/app/Tenancy/` and `web/app/Models/Tenant.php`:

- A `Tenant` model and `tenants` table (name, unique subdomain `slug`). Tenants are central records, so the model is not tenant-scoped.
- `TenantContext`: the current tenant for one request or one job. Code runs in a tenant with `$tenant->run(fn () => ...)`, which restores the previous tenant afterwards.
- A `tenant` route middleware that resolves the tenant from the subdomain of `TENANCY_DOMAIN` (default `localhost`). An unknown or missing subdomain returns 404. It runs before every other middleware, so the session save, authentication and route model binding are scoped too.
- A `BelongsToTenant` trait that:
  - adds a `TenantScope` global scope;
  - fills `tenant_id` from the current tenant on create;
  - refuses to create, update or delete a record outside a tenant context or for a different tenant, and refuses any change of `tenant_id`, including through a bulk `update()` or `increment()` (`TenantBuilder`).
  - With no tenant context, a query on a tenant-owned model throws instead of returning every tenant's rows (fail closed).
- `CrossTenant::query(Model::class)`: the one named way to query across tenants. Nothing else in `app/`, `routes/` or `database/` may remove the scope, skip model events, bulk-insert, or reach the database outside Eloquent (the `DB` facade, `getConnection()`, the `db` container service).
- Queued jobs: a `TenantAware` trait captures the current tenant id as a serialized property when the job is created. A job middleware runs the job inside that tenant and restores the previous context afterwards. A missing or deleted tenant fails the job.
- `users` becomes tenant-owned (D-002): a new migration adds `tenant_id`, and email becomes unique per tenant. Login and registration stay in feat-003.
- Test helpers: `twoTenants()` creates two tenants, and `tenantUrl()` builds a URL on a tenant's subdomain.

## How I Will Verify

- [ ] Layer 1 (static): `npm run lint` (Pint, ESLint) and `npm run typecheck` (Larastan level 8, vue-tsc) pass
- [ ] Layer 2 (runtime): `npm run test` runs Pest on SQLite in memory (D-005), so nothing depends on PostgreSQL. The tests show that a record created in tenant A is invisible to tenant B:
  - in queries (`find`, `count`, relations), with the write guards and the fail-closed behaviour;
  - through a route (route model binding returns 404 on tenant B's subdomain);
  - in queued jobs, sync and through a real `database` queue worker.
- [ ] Layer 2 (architecture): Pest tests fail when:
  - a model under `app/` (other than the central `Tenant`) lacks `BelongsToTenant`;
  - a tenant-owned table has no index that starts with `tenant_id`, or no foreign key to `tenants`;
  - a queued job under `app/` is not tenant-aware;
  - scope-bypass calls appear outside `CrossTenant`;
  - the `tenant` middleware is not sorted first.
  Each check, apart from the middleware order, also has a test that feeds it a violation and expects it to be reported.
- [ ] Layer 3 (system): `npm run check` and `npm run check-arch` pass; `npm run verify-feature -- feat-002` passes

## What Success Looks Like

feat-002 is `passing` in `.harness/state/feat-002.json`. A new tenant-owned model only needs `use BelongsToTenant` and a migration with an indexed `tenant_id`. Forgetting the trait or bypassing the scope fails `npm run test`.

## Dependencies

- [x] Depends on: feat-001 (passing)
- [x] Blocked by: none

## Risks

- SQLite and PostgreSQL differ. The tests use only portable schema and queries (D-005).
- Scope bypass detection is a token scan of our own code. It catches the Eloquent bypass methods, `*Quietly` writes, `DB` facade calls (aliased too), `getConnection()`, the `db` container service and dynamic method calls. It does not catch every raw SQL path, for example a hand-built PDO or a connection reached through an untyped variable. PostgreSQL row-level security stays a later option (D-002).
- Laravel internals that query without global scopes (queue model restoration, `fresh()`, the `unique` and `exists` validation rules) are outside the trait. Validation rules on tenant-owned tables need an explicit `tenant_id` condition; that is noted for feat-003.
- `password_reset_tokens` is keyed by email alone, which clashes with per-tenant emails. feat-003 owns password reset.

## Amended After Review

The independent review (session 003) found gaps in the first version, and the contract above now includes their fixes:
- a bulk `update()` could move rows to another tenant;
- the session was saved outside the tenant;
- the scan missed `getQuery()`, other `*Quietly` methods, aliased `DB` and the connection;
- the index rule had no violation test;
- the queued-job rule and the scan of `database/migrations` were missing.

## Commit Plan

One commit: tenancy code, the users migration, tests, docs and harness state together, after the author approves it.

---

_Signed off by: Claude (Opus 5.5) on 2026-10-19_
