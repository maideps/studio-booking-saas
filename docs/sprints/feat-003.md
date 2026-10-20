# Sprint Contract

**Feature ID:** feat-003
**Feature Name:** Authentication and roles
**Date:** 2026-10-19
**Estimated Duration:** 1 session

## What I Will Build

Sign-in for studio members on their tenant's subdomain, three roles, and policies that enforce them. Everything runs inside the tenant from feat-002 (D-002, D-007).

- **Routes.** Every auth route is behind the `tenant` middleware. On the bare domain or an unknown subdomain they return 404.
  - Login and logout.
  - Registration.
  - Forgot password and reset password.
  - A dashboard.
  - A members list, and a form to change a member's role.
- **Login.**
  - The user provider queries through the tenant scope, so credentials only match a user of the current tenant.
  - A session or remember-me cookie from tenant A does not authenticate on tenant B.
  - The session is regenerated on login and invalidated on logout.
  - Failed attempts are rate-limited. The limiter key includes the tenant id, so a lockout in tenant A does not lock the same email out of tenant B.
- **Registration.**
  - It creates a `customer` in the current tenant. The same email may already exist in another tenant.
  - `role` is not mass-assignable. A registration form that sends `role=owner` still creates a customer.
- **Roles.** An `App\Enums\Role` enum (`owner`, `staff`, `customer`) is stored in a new `users.role` column, which defaults to `customer`.
- **Policies.** `UserPolicy`:
  - `viewAny`: list members. Owner and staff.
  - `updateRole`: change a member's role. Owner only, and never their own role, so a studio cannot lose its last owner by accident.
  - A `Gate::before` check denies every ability to a user who does not belong to the current tenant, and to everyone when there is no tenant. Future policies get this check without writing it.
- **Password reset tokens per tenant** (closes a feat-002 risk).
  - `password_reset_tokens` is re-created as a tenant-owned table: `tenant_id`, `email`, `token`, `created_at`, and a unique index on (`tenant_id`, `email`).
  - It gets a `PasswordResetToken` model with `BelongsToTenant`, and a token repository built on that model, so tokens get the tenant scope and the write guards.
  - `auth:clear-resets` purges expired tokens of every tenant through `CrossTenant`.
  - The reset link is built on the tenant's own subdomain.
- **Tenant-aware validation rules** (closes a feat-002 risk).
  - `App\Tenancy\TenantRule::unique()` and `::exists()` add an explicit `tenant_id` condition, because Laravel's `unique` and `exists` rules skip global scopes.
  - An architecture rule fails on `Rule::unique`, `Rule::exists`, `new Unique`, `new Exists` or a `unique:` / `exists:` rule string anywhere else in `app/`, `routes/` or `database/`.
- **Routes rule.** An architecture rule fails when a route with `auth` or `guest` middleware lacks the `tenant` middleware.
- **Database sessions in tests** (closes a feat-002 risk). `phpunit.xml` switches the test session driver from `array` to `database`, the same as `.env.example`. Every login test therefore saves the session (with its `user_id`) through the database handler, inside the tenant.
- **Front end.** Minimal Inertia pages:
  - Login, Register, Forgot password and Reset password.
  - Dashboard and Members.
  - The shared props carry the signed-in user (with role) and the tenant.
  The full staff and customer UI stays in feat-012.
- **Seeder.** The demo tenant gets one owner, one staff member and one customer.

Out of scope:
- staff invitations and studio settings (feat-004);
- email verification;
- the audit log of role changes (feat-010);
- tenant signup.

## How I Will Verify

- [ ] Layer 1 (static): `npm run lint` (Pint, ESLint) and `npm run typecheck` (Larastan level 8, vue-tsc) pass.
- [ ] Layer 2 (runtime): `npm run test` runs Pest on SQLite in memory (D-005) and Vitest.
  - **Every protected action gets a test per role** (owner, staff, customer), **one for a user from another tenant**, and one for a guest. The protected actions are the dashboard, logout, the members list and the role change. The foreign-tenant user is tested two ways:
    - by replaying their real tenant-A session cookie on tenant B, which leaves them a guest;
    - by `actingAs`, which `Gate::before` must turn into a 403.
  - **Login:**
    - the same email in two tenants, with different passwords, signs in to each tenant only with that tenant's password;
    - tenant A's credentials fail on tenant B;
    - a tenant-A session or remember-me cookie is a guest on tenant B;
    - the rate limit is per tenant;
    - the login routes return 404 off a tenant subdomain.
  - **Registration:**
    - the same email can register in a second tenant;
    - a duplicate in the same tenant is rejected;
    - `role` cannot be set from the form.
  - **Password reset:**
    - tokens are stored per tenant;
    - the same email in two tenants gets independent tokens;
    - a tenant-A token cannot reset the tenant-B account;
    - the link points to the tenant's subdomain;
    - the throttle is per tenant;
    - expired tokens are purged across tenants.
  - **Architecture:** the validation-rule rule and the tenant-routes rule pass on the app, and each is also fed a violation.
- [ ] Layer 3 (system): `npm run check` and `npm run check-arch` pass. Then `npm run verify-feature -- feat-003` and `npm run vcr`.

## What Success Looks Like

feat-003 is `passing` in `.harness/state/feat-003.json`. A member signs in on `demo.localhost:8000` and sees the dashboard, and owners and staff see the members list. A session never carries over to another studio. A new policy only needs its own role rules, because the tenant check runs before it.

## Dependencies

- [x] Depends on: feat-002 (passing)
- [x] Blocked by: none

## Risks

- **Guard cache in tests.** In tests, Laravel keeps the guard's resolved user between requests. A cookie-replay test must reset the guards between requests, or it proves nothing. Each replay test also shows that the same cookie does authenticate on its own tenant.
- **Session cookie scope.** Session cookies are scoped to one host only while `SESSION_DOMAIN` is unset. A shared parent domain would send tenant A's cookie to tenant B. The scoped user lookup still keeps it a guest there, and the tests cover that case. The docs will say not to set `SESSION_DOMAIN`.
- **Laravel's `TokenRepositoryInterface`.** The broker manager has no hook for a custom token repository, so the app extends `PasswordBrokerManager` and overrides `createTokenRepository()`. A Laravel upgrade could change that protected method.
- **The validation-rule scan** is a token scan of our own code, like the bypass scan in feat-002. It catches the rule helpers, the rule classes and rule strings. A rule string built at runtime would pass it.

## Commit Plan

One commit: the auth code, migrations, tests, docs and harness state together, after the author approves it.

---

_Signed off by: Claude (Opus 5.5) on 2026-10-19_
