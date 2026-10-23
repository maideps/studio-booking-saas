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

Most PHP tests run on SQLite in memory (set in `web/phpunit.xml`, D-005). The tests that depend on PostgreSQL behaviour, such as row locks under parallel requests, are in the `postgres` group in `web/tests/Concurrency/`. On SQLite they are skipped.

`tools/test-postgres.mjs` runs that group against a throwaway PostgreSQL 17 container (`docker/test-postgres.compose.yml`, port 55432, data in memory) and removes the container afterwards. `npm run test` runs it after the SQLite suite, so `npm run test` needs Docker and fails clearly without it. These tests migrate a fresh database per test and commit their data, because they start separate PHP processes that must see it. The Compose stack and production-like runs use PostgreSQL.

Layering inside `web/app/` (dependencies point downward only):

- **Presentation** — HTTP controllers, Inertia pages, API resources, form requests.
- **Application** — actions (one use case per class), jobs, listeners.
- **Domain** — models and their rules, enums, domain events, policies. No dependency on Stripe, HTTP or the queue.
- **Infrastructure** — Stripe gateway, mail, storage; implements interfaces the domain defines.

Key design rules:

- **Tenancy:** one database, a `tenant_id` column on every tenant-owned table (including users), a global scope applied by a `BelongsToTenant` trait, tenant resolved from the request (subdomain) and carried explicitly into queued jobs (D-002, D-007; see Tenancy below).
- **Capacity:** booking inside a transaction with a row lock on the session; a concurrency test fires parallel requests and asserts no overbooking.
- **Billing:** Stripe sits behind a domain-owned `PaymentGateway` (D-004, D-012). Fulfilment is idempotent. Webhook handlers (feat-008) verify the Stripe signature, record each event id, and are safe to replay.
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
  - Every foreign key from a tenant-owned table to another tenant-owned table is composite, on (`tenant_id`, `<reference>_id`) to the parent's (`tenant_id`, `id`). The database then refuses a row of another tenant (D-009).
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
- **Shared Inertia props** (`HandleInertiaRequests`): `tenant` (name, slug) and `auth.user` (id, name, email, role). `auth.user.can` holds `viewMembers`, `manageClasses` and `updateSettings`, so the navigation only offers what the role may use. Both props are null off a tenant subdomain, where resolving the user would fail closed.
- **Tests.**
  - `Tests\Support\ProtectedActions` lists every route that needs a signed-in user, with the roles allowed and a valid payload. A test fails when a route with `auth` middleware is missing from the list.
  - Every action is run against a `StudioFixture` (one member per role, plus one record of each kind):
    - for each role: allowed roles succeed, the others get 403 (`web/tests/Feature/Authorization/RoleMatrixTest.php`);
    - for an owner of another studio (403);
    - for a guest (redirect to login);
    - for a replayed session of another studio (a guest).
  - A user of another tenant is tested two ways. A real tenant-A session cookie replayed on tenant B leaves them a guest. `actingAs` on tenant B gets a 403 from `Gate::before`.
  - In tests, Laravel keeps the guard's user and the session's attributes between requests. Cookie-replay tests call `startNewRequest()` (`web/tests/Pest.php`) first, and each one also shows that a request without the cookie is a guest.
  - The suite uses the `database` session driver (`phpunit.xml`).

### Studios, staff and classes

Studio settings, staff invitations, class templates and scheduled class sessions (feat-004, D-009). The code is in `web/app/Http/Controllers/` (`StudioSettingsController`, `InvitationController`, `JoinController`, `ClassTemplateController`, `ClassSessionController`), `web/app/Actions/Members/`, `web/app/Actions/Classes/` and the models `Invitation`, `ClassTemplate` and `ClassSession`.

- **Settings** (`/settings`, owners).
  - `timezone` and `contact_email` are columns on the central `tenants` table, next to `name`.
  - The controller always edits the tenant from the context, never one named in the request. `TenantPolicy::update` also checks that the tenant is the current one.
- **Invitations** (owners).
  - `POST /invitations` stores one pending invitation per (`tenant_id`, `email`); a new one replaces the old.
  - The invitation has a 40-character random token, of which only the SHA-256 hash is stored, and it expires after 7 days (`Invitation::VALID_DAYS`).
  - The `StaffInvitation` mail is sent right away (queued mail is feat-009). It links to `/join/{token}` on the studio's subdomain (`Tenant::url()`).
  - Inviting an email that is already an owner or staff member is refused. A customer may be invited to become staff.
  - `/join/{token}` looks the token up through the tenant scope, so the link only works on the sending studio. Wrong, expired and other-studio tokens are all a 404.
  - **Accepting** deletes the invitation:
    - with no account in the studio, the invitee creates one with the invited role and is signed in;
    - with an account, that account must be signed in, and is brought back to the link after logging in;
    - a different signed-in user gets a 403;
    - accepting never demotes an owner.
  - `DELETE /invitations/{invitation}` revokes one. The members page lists pending invitations to owners only.
- **Class templates** (`/classes`): name (unique per studio, through `TenantRule::unique`), description, `duration_minutes` and a default `capacity`. Every member can list them; owners and staff create and edit them (`ClassTemplatePolicy`).
- **Class sessions** (`/schedule`).
  - Each session has a template, an instructor, `starts_at`, `ends_at` and `capacity`. The end is always the start plus the template duration (`ScheduleClassSession`).
  - Every member sees upcoming sessions; owners and staff schedule and edit them (`ClassSessionPolicy`).
  - Validation requires the template and the instructor to be in this studio, through `TenantRule::exists`, and the instructor to be an owner or staff member. The start must be in the future.
- **Time.**
  - Times are stored as UTC timestamps.
  - Forms send and pages receive wall-clock time in the studio timezone, as `YYYY-MM-DDTHH:mm` (`Tenant::LOCAL_TIME_FORMAT`). `Tenant::parseLocalTime()` converts it to UTC and `Tenant::localTime()` converts back.
  - Changing the studio timezone keeps every session at the same instant.
- **Cross-tenant references.**
  - `class_sessions` references `class_templates` and `users`, and `invitations` references its inviter in `users`. Each of these is a composite foreign key on (`tenant_id`, `<reference>_id`), so the database refuses a reference to another studio's row even when validation is skipped.
  - `users` and `class_templates` have the unique (`tenant_id`, `id`) index these keys need.
  - An architecture rule (`TenancyArchitecture::crossTenantForeignKeys`) fails on a single-column foreign key between tenant-owned tables.
- **Not built yet:**
  - deleting templates or sessions;
  - bookings against the capacity (feat-005).

### Bookings

Customers book places in class sessions (feat-005, D-010). The code is `web/app/Actions/Bookings/BookSession.php`, `web/app/Models/Booking.php`, `BookingPolicy`, `BookingController` and `App\Exceptions\BookingRefused`.

- **Table.** `bookings` is tenant-owned: `class_session_id`, `customer_id`, `active` and timestamps.
  - Composite foreign keys (`tenant_id`, …) point to `class_sessions` and `users` (D-009). `class_sessions` has the unique (`tenant_id`, `id`) index they need.
  - `active` is `true` while a booking holds a place and `NULL` once it is released. It is never `false`.
  - The unique index `bookings_one_active_per_customer` on (`tenant_id`, `class_session_id`, `customer_id`, `active`) allows one active booking per customer and session. NULLs are distinct in unique indexes in PostgreSQL and SQLite, so released bookings never block rebooking.
- **Booking.** `BookSession` runs in one transaction:
  1. lock the session row (`lockForUpdate()`, `SELECT … FOR UPDATE`);
  2. refuse a session that has started;
  3. refuse a second active booking by the customer;
  4. count the active bookings and refuse when the session is full;
  5. insert the booking.

  Bookings for one session therefore run one after another, and the count never misses a booking still being committed. A racing duplicate that reaches the unique index gets the same "already booked" answer. Refusals are `BookingRefused` exceptions with a message for the customer.
- **HTTP.**
  - `POST /schedule/{classSession}/bookings` books the signed-in customer. `BookingPolicy::create` allows customers only, inside their own studio.
  - A refusal comes back as the `booking` error on the schedule.
  - The schedule shows the places left, whether the customer has booked each session, and a Book button.
- **Capacity edits.** A session's capacity cannot be set below its active bookings.
- **Tests.**
  - `web/tests/Feature/Bookings/BookingTest.php` covers the rules on SQLite.
  - `web/tests/Concurrency/ParallelBookingTest.php` proves them on PostgreSQL. It starts one PHP process per attempt (`web/tests/Support/Concurrency/attempt-booking.php`) and releases them all at once with a go file. Each attempt holds its transaction open for 300 ms after booking.
  - Eight customers racing for the last place get exactly one booking. Five identical requests from one customer get exactly one booking.
  - With the row lock removed, all eight customers got in. The test fails then.

### Cancellation and waitlist

Customers cancel bookings before a studio-defined deadline and wait in line for full sessions; a freed place goes to the first person waiting at once (feat-006, D-011). The code is in `web/app/Actions/Bookings/` (`CancelBooking`, `JoinWaitlist`, `LeaveWaitlist`, `FillFromWaitlist`), `web/app/Models/WaitlistEntry.php`, `WaitlistEntryPolicy` and `WaitlistController`.

- **The rule.** A session never has a free place while someone is waiting.
  - `FillFromWaitlist`, given a locked session, gives each free place to the first active entry in id order. It releases the entry (`active = NULL`) and books that customer.
  - `CancelBooking` calls it after releasing a booking, and `ScheduleClassSession` calls it after a capacity change, inside the same transaction.
- **The lock.** `ClassSession::lockedForChange()` (`SELECT … FOR UPDATE`) is the first step of every change to a session's places:
  - `BookSession`, `CancelBooking`, `JoinWaitlist`, `LeaveWaitlist`;
  - `ScheduleClassSession` for an existing session, which also refuses a capacity below the active bookings.
- **Cancellation.**
  - `DELETE /bookings/{booking}`, the booking's own customer only (`BookingPolicy::cancel`).
  - The deadline is the start minus `tenants.cancellation_window_hours` (0 to 168, default 12, on the settings page; `Tenant::cancellationDeadline()`).
  - After it, or for an already cancelled booking, the action refuses with a message.
- **Waitlist.**
  - `POST /schedule/{classSession}/waitlist` joins: customers only, and only for a full session that has not started, by someone who holds no place and is not already waiting.
  - `DELETE /waitlist/{waitlistEntry}` leaves: the entry's own customer.
  - `waitlist_entries` has composite foreign keys and a one-active-entry unique index, like `bookings`.
- **Schedule page.** Shows the number waiting. For a customer, it also shows Cancel (with the deadline in studio time) or "Waiting, number N" with Leave, and offers Join waitlist on a full session.
- **Tests.**
  - `web/tests/Feature/Bookings/CancellationTest.php` and `WaitlistTest.php` cover:
    - the window boundaries (inside, at the last moment, one second late, after the start, a 0-hour window);
    - promotion order, skipping a customer who left, and promotion on a capacity increase;
    - the join rules, isolation and the database constraints.
  - `web/tests/Concurrency/ParallelWaitlistTest.php` races on PostgreSQL:
    - a cancellation against four new bookings: the first waiting customer gets the place;
    - two cancellations at once: the first two waiting customers are promoted in order;
    - a cancellation against four waitlist joins: the rule holds whatever the order. With the session lock removed from `CancelBooking`, this test failed on both runs.

### Billing

Each studio sells a monthly membership and a class pack, bought through Stripe Checkout in test mode. An entitlement rule decides who may book (feat-007, D-012). The code is in `web/app/Billing/`, `web/app/Actions/Billing/`, the models `Checkout`, `Membership` and `CreditEntry`, `CheckoutPolicy` and `BillingController`.

- **Pricing.** Set on the settings page and stored on `tenants`: `currency`, `membership_price_cents`, `class_pack_size` and `class_pack_price_cents`. An empty price means not sold, and a studio that sells nothing is free to book (`Tenant::isFreeToBook()`).
- **Gateway.** `PaymentGateway` has two operations: `startCheckout()`, which returns the hosted page URL, and `checkoutResult()`, which reports whether it was paid and, for a membership, the subscription and period end.
  - `StripeGateway` (stripe-php, Checkout Sessions, inline `price_data`; `subscription` mode for the membership, `payment` mode for a pack) is used when `STRIPE_SECRET` is set.
  - Otherwise `FakePaymentGateway` is used, and only in `local` and `testing`. Locally it auto-pays, so the demo works without Stripe.
  - `phpunit.xml` forces the fake.
- **Flow.**
  - `POST /billing/checkout/{membership|class_pack}` (customers) creates a pending `checkouts` row at the studio's price, never one from the request, and redirects to the hosted page.
  - The success URL is `/billing/checkouts/{checkout}/return` on the studio's subdomain. That route runs `FulfilCheckout`, which asks the gateway about the session id stored on the checkout row. If it was paid, it locks the row and, unless the checkout is already completed, either activates the customer's `memberships` row or adds a `purchase` entry of N credits. Then it marks the checkout completed.
  - Only the checkout's own customer can open the return route (`CheckoutPolicy::complete`). `credit_entries.checkout_id` is unique, so the database refuses a second credit for one purchase.
- **Entitlement** (`App\Billing\Entitlements`).
  - The rule: a free studio books with `paid_with = free`; otherwise an active, unexpired membership (`membership`); otherwise one credit (`credit`, a `-1` ledger entry for the booking); otherwise "Buy a membership or a class pack to book."
  - `BookSession` locks the customer row after the session row before spending, so parallel bookings cannot spend one credit twice.
  - `JoinWaitlist` requires an entitlement, but spends nothing.
  - `FillFromWaitlist` charges the promoted customer, and skips one who is no longer entitled.
  - `CancelBooking` refunds a credit with a `+1` `refund` entry.
  - The ledger is append-only, and the balance is the sum of `change`.
- **Pages.** `/billing` (customers) shows the offers, the membership and the credit balance. The navigation shows Billing to customers.
- **Not built yet:** webhooks, renewals, payment failures and the grace period (feat-008). Until then a membership lasts until the period end recorded at checkout.
- **Tests.**
  - `web/tests/Feature/Billing/` covers checkout, fulfilment, entitlement and the Stripe gateway. The gateway test runs against hand-written JSON fixtures (`web/tests/Fixtures/Stripe/`) through a recorded stripe-php HTTP client, so no network is used.
  - `web/tests/Concurrency/ParallelBillingTest.php` shows on PostgreSQL that one customer booking four sessions at once spends a single credit once (without the customer lock, all four got in), and that four parallel fulfilments credit a pack once.
