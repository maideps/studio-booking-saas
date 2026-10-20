# Architecture

## Layer Model

This project follows a layered structure:

```
┌─────────────────────────────────────────────┐
│               Presentation Layer             │
│  (UI, CLI, API endpoints — user-facing)     │
├─────────────────────────────────────────────┤
│               Application Layer              │
│  (Use cases, orchestration, workflows)       │
├─────────────────────────────────────────────┤
│                 Domain Layer                  │
│  (Business logic, entities, rules)           │
├─────────────────────────────────────────────┤
│             Infrastructure Layer             │
│  (Persistence, networking, external APIs)    │
└─────────────────────────────────────────────┘
```

## Harness Boundaries

The harness contract (described in `.harness/manifest.json` and enforced by `make check-arch` / `npm run check-arch`) separates:

- **CORE** — files that ship as-is and must not be edited (runner, scripts, templates, root operating manual).
- **TEMPLATES** — adopter skeletons under `templates/`.
- **INSTANCE** — this project's own state (feature list, progress, decisions, docs).
- **PRODUCT-OWNED** — directories listed in the manifest's `productRoots`.

Verification entrypoints delegate to the Node runner (`scripts/framework-check.mjs`); Makefile targets and npm scripts are 1:1 mirrors.

## Worktree Lanes

Multiple agents can work concurrently, each in its own sibling git worktree on branch `lane/<feature-id>` (`make lane-new F=<id>`, `lane-list`, `lane-land F=<id>`, `lane-drop F=<id>`). A branch can only be checked out in one worktree at a time, so git's own branch-checkout exclusivity is the coordination primitive — no lock file needed. WIP=1 is enforced per lane: a `lane/<feature-id>` branch may only activate the feature it is named for.

## Sharded Feature State

`feature_list.json` declares what each feature is and rarely changes; each feature's mutable state (`state`, `evidence`) lives in its own file, `.harness/state/<feature-id>.json`. `verify-feature` writes only that shard, never the shared declarations file — two lanes finishing two different features merge without conflict. `npm run migrate-feature-state` moves an older repo's inline state into shards.

## Application Dependency Rules

- Layers may only depend on layers below them
- Domain layer has zero external dependencies
- Infrastructure implements interfaces defined by domain
- Presentation never directly accesses infrastructure

Encode these rules as entries in `.harness/arch-rules.json` as the codebase grows.

## Enforcement

```bash
make check-arch     # or: npm run check-arch
```

## Module Documentation

Co-locate architecture notes with the modules they affect; record framework-wide decisions in the root `DECISIONS.md`.


## Product Architecture

```
Browser (Vue 3 + TypeScript via Inertia)  --->  Laravel app  --->  PostgreSQL
Booking widget (public JSON API)          --->       |
                                                     +--->  Redis (queues, cache, locks)  --->  Queue worker + scheduler
                                                     +--->  Stripe (test mode) <--- webhooks
                                                     +--->  Mailpit (local email capture)
```

Product directories (declared in `.harness/manifest.json` as `productRoots`):

- `web/` — the Laravel application (PHP and Vue sources, `composer.json`, its own `package.json`). It lives in a subfolder because the repository root `package.json` belongs to the harness.
  - Inertia pages are in `web/resources/js/pages/` and are named after the screen they render; `web/resources/js/app.ts` resolves them.
  - PHP tests (Pest) are in `web/tests/Feature` and `web/tests/Unit`, which includes architecture tests such as "every class in `App` uses strict types". Front-end tests (Vitest) are in `web/tests/js`.
  - Tool configuration lives next to the code: `pint.json`, `phpstan.neon`, `eslint.config.js`, `tsconfig.json`, `vite.config.ts`, `vitest.config.ts`.
- `tools/` — small Node launchers that run PHP and Node tooling from the harness gates on any operating system:
  - `php.mjs` runs PHP in `web/` (Pint, Larastan, Pest).
  - `web.mjs` runs `web/package.json` scripts (ESLint, vue-tsc, Vitest, Vite).
  - `setup-web.mjs` installs `web/` (`npm run setup:web`).
  - `compose-smoke.mjs` runs the Compose stack smoke test (`npm run smoke`).
  - On Windows, PHP, Composer and npm are `.bat`/`.cmd` shims, so the launchers start them through a shell.
- `docker/`, `docker-compose.yml` — local stack (app, queue worker, scheduler, PostgreSQL, Redis, Mailpit).
  - `docker/app/Dockerfile` builds one image for the three PHP roles: Vite assets in a Node stage, then PHP 8.3 with `pdo_pgsql` and `redis`. It runs as `www-data`.
  - No `APP_KEY` is committed. When none is set, the entrypoint generates one into the shared `app-secrets` volume. The worker and scheduler start only after the app is healthy, so they read the same key.
  - The app runs migrations on start.
- `docs/sprints/` — one sprint contract per feature.

The root `lint`, `typecheck`, `test` and `build` scripts run the harness's own check first, then the product tools through `tools/`. Each tool runs only after the previous one passes.

PHP tests run on SQLite in memory (set in `web/phpunit.xml`, D-005), so `npm run test` needs no containers. The Compose stack and production-like runs use PostgreSQL. Tests that depend on PostgreSQL behaviour, such as the row locks in feat-005, will need a PostgreSQL test database.

Layering inside `web/app/` (dependencies point downward only):

- **Presentation** — HTTP controllers, Inertia pages, API resources, form requests.
- **Application** — actions (one use case per class), jobs, listeners.
- **Domain** — models and their rules, enums, domain events, policies. No dependency on Stripe, HTTP or the queue.
- **Infrastructure** — Stripe gateway, mail, storage; implements interfaces the domain defines.

Key design rules:

- **Tenancy:** one database, a `tenant_id` column on every tenant-owned table (including users), a global scope applied by a `BelongsToTenant` trait, tenant resolved from the request (subdomain) and carried explicitly into queued jobs (D-002, D-007; see Tenancy below).
- **Capacity:** booking inside a transaction with a row lock on the session; a concurrency test fires parallel requests and asserts no overbooking.
- **Billing:** webhook handlers verify the Stripe signature, record each event id, and are safe to replay.
- **Audit:** state changes append rows to an audit table that is never updated or deleted.

### Tenancy

The mechanism from D-002 and D-007 lives in `web/app/Tenancy/` and `web/app/Models/Tenant.php`.

- **Tenants** are central records (`tenants` table: `name`, unique `slug`). The slug is the subdomain: the studio `acme` is served on `acme.<TENANCY_DOMAIN>`.
  - `TENANCY_DOMAIN` is set in `config/tenancy.php` and defaults to `localhost`. It is a bare host name, without a port.
  - Browsers resolve `*.localhost` to the local machine, so the seeded demo tenant is `http://demo.localhost:8000` in the Compose stack, after `php artisan db:seed`.
- **Context:** `TenantContext` holds the current tenant.
  - It is a scoped instance, so each request and each queued job starts with none.
  - Code enters a tenant only with `$tenant->run(fn () => ...)`. It restores the previous tenant afterwards, even when the callback throws, and refuses a tenant that was never saved.
- **Requests:** tenant routes use the `tenant` middleware (`ResolveTenantFromSubdomain`).
  - It accepts exactly `<slug>.<TENANCY_DOMAIN>`. Any other host is a 404: the bare domain, an unknown slug, a nested subdomain, or another domain.
  - It is sorted first in the middleware priority list. The session save, authentication and route model binding therefore all run inside the tenant. An architecture test checks the order.
- **Models:** a tenant-owned model uses `BelongsToTenant`, and its table has an indexed `tenant_id` that references `tenants`. `users` is tenant-owned, and email is unique per tenant. The trait:
  - adds `TenantScope`, which limits every query, including relation and `whereHas` queries, to the current tenant. With no tenant it throws `TenancyViolation` (fail closed).
  - fills `tenant_id` on create;
  - refuses creates, updates and deletes outside a tenant context or for another tenant, and any change of `tenant_id`;
  - gives the model a `TenantBuilder`, which refuses a bulk `update()`, `increment()` or `decrement()` that writes `tenant_id`. Bulk writes skip model events, so the trait's guards would not see them.
- **Cross-tenant reads:** `CrossTenant::query(Model::class)` is the only sanctioned way to drop the scope. It is for platform-level work, such as a scheduled job that fans out per tenant.
- **Queued jobs:** a tenant-aware job implements `TenantAwareJob`, uses the `TenantAware` trait and calls `captureTenant()` in its constructor.
  - The tenant id is a serialized property of the job.
  - On the worker, the `RunInTenantContext` job middleware runs the job inside that tenant and restores the previous context.
  - A job created outside a tenant, one that never captured its tenant, or one whose tenant was deleted fails.
  - A job that defines its own `middleware()` must include `RunInTenantContext`.
  - Pass ids, not models with loaded tenant-owned relations: Laravel reloads those relations before job middleware runs.
- **What runs without a tenant** (and so fails closed on any tenant-owned query until it enters one):
  - console commands and scheduled tasks;
  - queued notifications, mailables, listeners and broadcasts that are not built as `TenantAwareJob`;
  - a job's `failed()` handler, which runs outside job middleware;
  - `defer()` callbacks, terminate callbacks and streamed response bodies, which run after the middleware has restored the context.
- **Enforcement:** the tests are in `web/tests/Feature/Tenancy/TenancyArchitectureTest.php`, and the checks they run are in `web/tests/Support/TenancyArchitecture.php`.
  - Every Eloquent model under `app/`, except the central `Tenant`, uses `BelongsToTenant`.
  - Every tenant-owned table has an index that starts with `tenant_id`, and a foreign key from it to `tenants`.
  - Every queued job under `app/` is a `TenantAwareJob`, unless it is listed in `CENTRAL_JOBS`.
  - No code in `app/`, `routes/` or `database/` other than `CrossTenant`:
    - drops global scopes (`withoutGlobalScope*`, `newQueryWithoutScope*`, `newModelQuery`, `getQuery`);
    - skips model events (`withoutEvents`, any `*Quietly` method, `WithoutModelEvents`);
    - bulk-inserts (`insert`, `upsert` and similar);
    - reaches the database outside Eloquent: `DB::` query calls (aliased too), `getConnection()`, `app('db')`, or the `DatabaseManager` and connection types;
    - calls a method dynamically (`->{...}()`, `->$method()`).
    `DB::transaction` is fine.
  - No code in `app/`, `routes/` or `database/` other than `TenantRule` uses the `unique` or `exists` validation rules (`Rule::unique`, `Rule::exists`, `new Unique`, `new Exists`, or a `unique:` / `exists:` rule string). Laravel checks them without global scopes (D-008).
  - Every route with `auth`, `guest` or `can` middleware also has the `tenant` middleware (D-008).
  - Every check except the middleware order also has a test that feeds it a violation.
- **Known limits:**
  - The bypass and validation-rule checks are token scans of our own code, not a proof. A hand-built PDO, a connection reached through an untyped variable, or a rule string built at runtime would pass them.
  - Laravel internals that skip global scopes are outside the trait: queue model restoration, `fresh()` and `refresh()`, and the `unique` and `exists` validation rules. Use `TenantRule` for the validation rules.
  - Cache keys and file storage paths are not tenant-prefixed. Code that caches or stores tenant data must include the tenant id itself.
  - PostgreSQL row-level security remains a later option (D-002).
- **Tests:** `twoTenants()` creates `studio-a` and `studio-b`, and `tenantUrl($tenant, $path)` builds a URL on a tenant's subdomain (`web/tests/Pest.php`). `phpunit.xml` pins `TENANCY_DOMAIN=localhost`.

### Authentication and roles

Login, registration and roles from D-008 build on the tenancy mechanism. The code is in `web/app/Http/Controllers/Auth/`, `web/app/Auth/`, `web/app/Policies/` and `web/app/Enums/Role.php`.

- **Routes** (`web/routes/web.php`). Every route that needs a signed-in user, and every guest-only auth page, is inside one `tenant` middleware group. On the bare domain or an unknown subdomain they return 404.
  - Guest pages: `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`.
  - Signed-in pages: `/dashboard`, `POST /logout`, `/members` and `PATCH /members/{member}/role`.
  - An architecture rule (`TenancyArchitecture::routesOutsideTenant`) fails on any route with `auth`, `guest` or `can` middleware outside the group.
- **Users and login.**
  - The Eloquent user provider queries through the tenant scope. Credentials, the session's user id and the remember-me token therefore only resolve to a user of the current tenant. A cookie from tenant A is a guest on tenant B.
  - Session cookies are host-only while `SESSION_DOMAIN` is unset. Leave it unset: a shared parent domain would send every studio's cookie to every other studio. The scoped lookup would still keep them guests.
  - Login regenerates the session id, and logout invalidates the session.
  - Failed logins are throttled per tenant, email and IP (`LoginRequest::MAX_ATTEMPTS`). The cache key includes the tenant id.
- **Roles.** `App\Enums\Role` has `owner`, `staff` and `customer`, stored in `users.role` (default `customer`). Registration always creates a customer. `role` is not fillable, and only `ChangeMemberRole` sets it.
- **Policies.**
  - `Gate::before` (in `AppServiceProvider`) denies every ability to a user who does not belong to the current tenant, and to everyone when there is no tenant.
  - `UserPolicy`: `viewAny` (list members) allows owners and staff. `updateRole` allows owners only, and never on their own account.
  - Routes authorize with `->can(...)`, and pages receive per-row flags such as `canUpdateRole` so the UI matches the policy.
- **Password reset.**
  - `password_reset_tokens` is tenant-owned (`tenant_id`, `email`, hashed `token`, `created_at`; unique `tenant_id, email`).
  - `TenantTokenRepository` reads and writes it through the `PasswordResetToken` model, so the tenant scope and write guards apply. `TenantPasswordBrokerManager` installs it in place of Laravel's `DatabaseTokenRepository`.
  - `php artisan auth:clear-resets` purges expired tokens of every tenant through `CrossTenant`.
  - The reset link is built from the user's tenant with `Tenant::url()` (scheme and port from `APP_URL`), so it is right outside a request too.
  - The forgot-password form answers the same way whether or not the email is a member.
- **Validation.** `unique` and `exists` rules on tenant-owned tables go through `TenantRule::unique()` / `TenantRule::exists()`, which add the current `tenant_id`. An architecture rule fails on any other use of those rules in `app/`, `routes/` or `database/`.
- **Shared Inertia props** (`HandleInertiaRequests`): `tenant` (name, slug) and `auth.user` (id, name, email, role, `canViewMembers`). Both are null off a tenant subdomain, where resolving the user would fail closed.
- **Tests.**
  - `web/tests/Feature/Auth/` and `web/tests/Feature/Members/` cover every protected action for each role, for a guest and for a user of another tenant. `Tests\Support\ProtectedActions` lists the actions, and a test fails when a route with `auth` middleware is missing from that list.
  - A user of another tenant is tested two ways. A real tenant-A session cookie replayed on tenant B leaves them a guest. `actingAs` on tenant B gets a 403 from `Gate::before`.
  - In tests, Laravel keeps the guard's user and the session's attributes between requests. Cookie-replay tests call `startNewRequest()` (`web/tests/Pest.php`) first, and each one also shows that a request without the cookie is a guest.
  - The suite uses the `database` session driver (`phpunit.xml`).
