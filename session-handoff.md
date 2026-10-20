# Session Handoff

Fill this at session end for large sessions; keep it current otherwise.

## Current Objective

- Goal: build Studio Booking SaaS, a multi-tenant booking and membership platform (Laravel, Vue, PostgreSQL, Stripe test mode), as a portfolio project built with AI coding agents using ai-harness-framework, one feature at a time.
- Current status: feat-001, feat-002 and feat-003 (authentication and roles) are `passing`. No feature is active. Next is feat-004 (studios, staff and classes).
- Branch / commit: `main`, pushed to the private GitHub repository `maideps/studio-booking-saas` (`origin`) up to `00e8eea`. **feat-003 is not committed yet**: it waits for the author's go-ahead.

## Completed This Session

- [x] Baseline: `./init.sh`, including the full `npm run check`, passed on a clean tree at `00e8eea` before any edit.
- [x] feat-003, per the new D-008 (details in `docs/ARCHITECTURE.md`, section Authentication and roles):
  - login, registration, forgot and reset password, dashboard, members list and role change, all behind the `tenant` middleware;
  - the `Role` enum (owner, staff, customer) on `users.role`; registration always creates a customer;
  - `UserPolicy`, plus a `Gate::before` check that denies users outside the current tenant;
  - per-tenant password reset tokens (`PasswordResetToken`, `TenantTokenRepository`, `TenantPasswordBrokerManager`), with reset links built by `Tenant::url()`;
  - `TenantRule` for `unique` / `exists` rules;
  - a per-tenant login throttle;
  - minimal Inertia pages and shared `tenant` / `auth.user` props;
  - a seeder with one demo member per role.
- [x] The feat-002 risks are closed:
  - reset tokens are keyed per tenant;
  - the validation rules are tenant-aware, and an architecture rule enforces it;
  - the auth routes are behind `tenant`, and an architecture rule enforces it;
  - the tests run on the `database` session driver.
- [x] Tests:
  - every protected action for each role, a guest, a replayed tenant-A session on tenant B, and `actingAs` a foreign user for the policy actions;
  - the same email in two tenants;
  - cross-tenant sign-in;
  - password reset isolation.
  14 deliberate breaks were each caught by a test.
- [x] Review pass, done inline: the reset error no longer reveals membership, and there is a regression test for `/` with a signed-in session. The independent review agent was stopped by the author before it reported.

## Verification Evidence

| Check | Command | Result | Notes |
|---|---|---|---|
| Full verification | `npm run check` | PASS | Pint, ESLint, Larastan, vue-tsc, Pest (116 tests, 414 assertions), Vitest (6 tests), Vite build and the harness's own tests all ran |
| Feature gate | `npm run verify-feature -- feat-003` | PASS | Evidence in `.harness/state/feat-003.json`. The SKIPs in Layer 3 are the harness's adoption-matrix toolchains (Python, Go, Rust) and its example multi-repo entry, not product checks |
| Trail | `npm run vcr` | PASS | `.harness/trails/2026-10-20T13-23-56-224Z-vcr.json` |
| Compose stack | `npm run smoke` | PASS | The app migrated PostgreSQL, including the `role` and `password_reset_tokens` migrations |
| Architecture rules | `npm run check-arch` | PASS | It warns that untracked files are only checked once added to git |

## Files Changed

- New:
  - `web/app/Actions/`, `web/app/Auth/`, `web/app/Enums/Role.php`, `web/app/Policies/UserPolicy.php`, `web/app/Tenancy/TenantRule.php`, `web/app/Models/PasswordResetToken.php`
  - `web/app/Http/Controllers/Auth/`, `DashboardController`, `MemberController`, `MemberRoleController`, `web/app/Http/Requests/`
  - two migrations (`2026_10_19_000002`, `2026_10_19_000003`)
  - `web/resources/js/layouts/`, `pages/Auth/`, `pages/Dashboard.vue`, `pages/Members/`, `types/inertia.d.ts`
  - `web/tests/Feature/Auth/`, `web/tests/Feature/Members/`, `web/tests/Feature/Tenancy/TenantRuleTest.php`, `web/tests/Datasets/`, `web/tests/Support/ProtectedActions.php`, `web/tests/js/Login.test.ts`, `web/tests/js/MembersIndex.test.ts`
  - `docs/sprints/feat-003.md`, `progress/004-2026-10-19.md`, `.harness/state/feat-003.json`
- Changed:
  - `web/app/Models/User.php`, `Tenant.php`, `web/app/Tenancy/BelongsToTenant.php`, `web/app/Providers/AppServiceProvider.php`, `web/app/Http/Middleware/HandleInertiaRequests.php`, `web/routes/web.php`
  - `web/database/factories/UserFactory.php`, `web/database/seeders/DatabaseSeeder.php`
  - `web/phpunit.xml` (database sessions), `web/phpstan.neon` (`parseModelCastsMethod`)
  - `web/tests/Pest.php`, `web/tests/Support/TenancyArchitecture.php`, `web/tests/Feature/Tenancy/TenancyArchitectureTest.php`, `web/tests/Feature/DatabaseSeederTest.php`
  - `README.md`, `DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/quality-document.md`, `PROGRESS.md` (generated)

## Decisions Made

- D-008:
  - Authentication runs inside the tenant.
  - `Gate::before` denies users of other tenants.
  - Reset tokens are a tenant-owned model behind a custom token repository.
  - `unique` / `exists` rules go only through `TenantRule`.
  - The login throttle is keyed per tenant.
  - Tests use database sessions.

## Blockers / Risks

- **From feat-003:**
  - `TenantPasswordBrokerManager` overrides Laravel's protected `createTokenRepository()`; recheck it on Laravel upgrades (the password reset tests would fail).
  - Central tables (for example tenant slugs at signup) will need a reviewed exception to the `TenantRule` architecture rule.
  - `/` on a tenant subdomain shows the central welcome page.
  - The members list has no pagination.
  - Only the Login and Members pages have component tests.
  - Do not set `SESSION_DOMAIN` to a parent domain (see `docs/ARCHITECTURE.md`).
- **Not handled yet (from feat-002):**
  - Tenant slugs are not validated against `Tenant::SLUG_PATTERN` and none are reserved.
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

- Commit feat-003 once the author approves it. Suggested message: `feat(feat-003): tenant-scoped authentication, roles and policies`.
- Then start feat-004 (studios, staff and classes):
  - Write `docs/sprints/feat-004.md` first, then `npm run activate -- feat-004`.
  - Staff invitations can build on `ChangeMemberRole` and `UserPolicy`.
  - New policies get the tenant check from `Gate::before`.
  - Add each new protected route to `tests/Support/ProtectedActions.php`; a test fails if one is missing.
- The GitHub repository is private for now. Make it public when the project is ready to show: `gh repo edit maideps/studio-booking-saas --visibility public --accept-visibility-change-consequences`.
