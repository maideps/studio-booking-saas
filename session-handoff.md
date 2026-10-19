# Session Handoff

Fill this at session end for large sessions; keep it current otherwise.

## Current Objective

- Goal: build Studio Booking SaaS, a multi-tenant booking and membership platform (Laravel, Vue, PostgreSQL, Stripe test mode), as a portfolio project built with AI coding agents using ai-harness-framework, one feature at a time.
- Current status: feat-001 and feat-002 (tenancy foundation) are `passing`. No feature is active. Next is feat-003 (authentication and roles).
- Branch / commit: `main`, local repository only, with no GitHub remote yet. feat-001 is committed as `3014156` and feat-002 as `8ade887`. Nothing has been pushed.

## Completed This Session

- [x] Baseline: `./init.sh`, including the full `npm run check`, passed on a clean tree before any edit.
- [x] feat-002, per D-002 and the new D-007 (details in `docs/ARCHITECTURE.md`, section Tenancy):
  - the `Tenant` model;
  - the subdomain middleware `tenant`, sorted first;
  - `TenantContext` and `$tenant->run()`;
  - the `BelongsToTenant` trait, with a fail-closed scope, write guards and `TenantBuilder`;
  - `CrossTenant::query()`;
  - tenant-aware queued jobs;
  - `users` made tenant-owned, with email unique per tenant;
  - the test helpers `twoTenants()` and `tenantUrl()`.
- [x] Isolation tests (queries, relations, bulk updates, routes, jobs, including a real `database` worker) and architecture tests, each check also fed a violation.
- [x] Independent review pass; its high and normal findings were fixed (see `progress/003-2026-10-19.md`).

## Verification Evidence

| Check | Command | Result | Notes |
|---|---|---|---|
| Full verification | `npm run check` | PASS | Pint, ESLint, Larastan, vue-tsc, Pest (47 tests, 99 assertions), Vitest (1 test), Vite build and the harness's own tests all ran |
| Feature gate | `npm run verify-feature -- feat-002` | PASS | Evidence in `.harness/state/feat-002.json`. The SKIPs in Layer 3 are the harness's adoption-matrix toolchains (Python, Go, Rust) and its example multi-repo entry, not product checks |
| Trail | `npm run vcr` | PASS | `.harness/trails/2026-10-19T22-24-26-753Z-vcr.json` |
| Compose stack | `npm run smoke` | PASS | The app migrated PostgreSQL, including the new `users` migration |
| Architecture rules | `npm run check-arch` | PASS | |

## Files Changed

- New:
  - `web/app/Tenancy/` (context, scope, trait, builder, `CrossTenant`, violation, `Queue/`)
  - `web/app/Models/Tenant.php`, `web/app/Http/Middleware/ResolveTenantFromSubdomain.php`, `web/config/tenancy.php`
  - `web/database/factories/TenantFactory.php`, two migrations (`2026_10_19_000000`, `2026_10_19_000001`)
  - `web/tests/Feature/Tenancy/`, `web/tests/Feature/DatabaseSeederTest.php`, `web/tests/Support/`, `web/tests/Fixtures/`
  - `docs/sprints/feat-002.md`, `progress/003-2026-10-19.md`, `.harness/state/feat-002.json`
- Changed:
  - `web/app/Models/User.php`, `web/app/Providers/AppServiceProvider.php`, `web/bootstrap/app.php`, `web/database/seeders/DatabaseSeeder.php`
  - `web/tests/Pest.php`, `web/phpunit.xml`, `web/.env.example`
  - `README.md`, `DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/quality-document.md`, `PROGRESS.md` (generated)

## Decisions Made

- D-007:
  - The tenant scope fails closed (it throws when there is no tenant).
  - Tenant writes are guarded, bulk writes of `tenant_id` included.
  - The `tenant` middleware sorts before every other middleware.
  - Jobs carry their tenant id explicitly.
  - The architecture test defines what counts as a scope bypass.
  - `users` became tenant-owned in this feature.

## Blockers / Risks

- **For feat-003:**
  - `password_reset_tokens` is keyed by email alone, which clashes with per-tenant emails.
  - `unique` and `exists` validation rules on `users` need an explicit `tenant_id` condition.
  - Login and registration routes must use the `tenant` middleware.
  - Add a login test with the database session driver.
- **Not handled yet:**
  - Tenant slugs are not validated against `Tenant::SLUG_PATTERN` and none are reserved (needed when tenant signup is built).
  - A `TENANCY_DOMAIN` with a port is not rejected.
  - A job that overrides `middleware()` without `RunInTenantContext` is not detected (it still fails closed).
  - Cache keys and file storage are not tenant-prefixed.
- **Before feat-005:** move the PHP tests (at least the capacity tests) to a PostgreSQL test database (D-005).
- Carried over:
  - Herd's `php.ini` Redis line may come back after a Herd update.
  - The `e2e` layer is still the harness's own suite until feat-013.
  - The `LICENSE` copyright line is still undecided.

## Next Session Startup

1. Read `AGENTS.md`.
2. Read `feature_list.json`, `PROGRESS.md`, `DECISIONS.md` and `docs/STANDARDS.md`.
3. Review this handoff.
4. Run `npm run setup:web` on a fresh clone, then `./init.sh` and `npm run check` before editing.

## Recommended Next Step

- Start feat-003 (authentication and roles):
  - Write `docs/sprints/feat-003.md` first, then `npm run activate -- feat-003`.
  - Put the auth routes behind the `tenant` middleware.
  - Use tenant-aware validation rules.
  - Re-key password reset tokens per tenant.
- Create the GitHub repository when ready: `gh repo create maideps/studio-booking-saas --public --source . --push`.
