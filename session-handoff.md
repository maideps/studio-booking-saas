# Session Handoff

Fill this at session end for large sessions; keep it current otherwise.

## Current Objective

- Goal: build Studio Booking SaaS, a multi-tenant booking and membership platform (Laravel, Vue, PostgreSQL, Stripe test mode), as a portfolio project built with AI coding agents using ai-harness-framework, one feature at a time.
- Current status: feat-001 to feat-005 are `passing`; feat-005 is bookings with capacity locking. No feature is active. Next is feat-006 (cancellation and waitlist).
- Branch / commit: `main`, pushed to the private GitHub repository `maideps/studio-booking-saas` (`origin`). Commits so far: feat-001 `3014156`, feat-002 `8ade887`, feat-003 `5121d98`, feat-004 `ce4ea5a`, and feat-005 in the commit right after `ce4ea5a` (`feat(feat-005): ...`).

## Completed This Session

- [x] **PostgreSQL test database** (D-005).
  - `tools/test-postgres.mjs` runs the `postgres` Pest group (`web/tests/Concurrency/`) against a throwaway PostgreSQL 17 container (`docker/test-postgres.compose.yml`).
  - `npm run test` runs it after the SQLite suite, so `npm run test` needs Docker now.
- [x] **feat-005**, per the new D-010 (details in `docs/ARCHITECTURE.md`, section Bookings):
  - the `bookings` table with composite foreign keys and a one-active-booking unique index (`active` is `true` or `NULL`);
  - the `BookSession` action, which locks the session row and refuses full, started and already-booked sessions;
  - a customer-only `BookingPolicy`;
  - the `POST /schedule/{classSession}/bookings` route;
  - places left and booked state on the schedule;
  - capacity that cannot drop below the bookings.
- [x] **Tests.**
  - Parallel PHP processes on PostgreSQL: 8 customers for the last place get exactly 1 booking, and 5 identical requests get exactly 1. With the row lock removed, all 8 got in, and the test failed.
  - SQLite rule tests.
  - The booking route in the role matrix.
  - Vitest for the schedule.
  - 11 deliberate breaks were each caught.

## Verification Evidence

| Check | Command | Result | Notes |
|---|---|---|---|
| Full verification | `npm run check` | PASS | Pint, ESLint, Larastan, vue-tsc, Pest on SQLite (281 tests, 1,033 assertions), Pest on PostgreSQL (3 tests), Vitest (13 tests), Vite build and the harness's own tests all ran |
| Feature gate | `npm run verify-feature -- feat-005` | PASS | Evidence in `.harness/state/feat-005.json`. The SKIPs in Layer 3 are the harness's adoption-matrix toolchains and its example multi-repo entry, not product checks |
| Trail | `npm run vcr` | PASS | `.harness/trails/2026-10-20T16-13-32-875Z-vcr.json` |
| Compose stack | `npm run smoke` | PASS | The app migrated PostgreSQL with the bookings migration |
| Architecture rules | `npm run check-arch` | PASS | |

## Files Changed

- New:
  - `tools/test-postgres.mjs`, `docker/test-postgres.compose.yml`
  - `web/app/Models/Booking.php`, `web/database/factories/BookingFactory.php`, migration `2026_10_20_000004_create_bookings_table.php`
  - `web/app/Actions/Bookings/BookSession.php`, `web/app/Exceptions/BookingRefused.php`, `web/app/Policies/BookingPolicy.php`, `web/app/Http/Controllers/BookingController.php`
  - `web/tests/Concurrency/ParallelBookingTest.php`, `web/tests/Support/Concurrency/attempt-booking.php`, `web/tests/Feature/Bookings/BookingTest.php`, `web/tests/js/ScheduleIndex.test.ts`
  - `docs/sprints/feat-005.md`, `progress/006-2026-10-20.md`, `.harness/state/feat-005.json`, the vcr trail
- Changed:
  - `package.json` (the `test` script), `web/phpunit.xml` (the Concurrency suite), `web/tests/Pest.php`
  - `web/app/Models/ClassSession.php`, `web/app/Http/Controllers/ClassSessionController.php`, `web/app/Http/Requests/Classes/ClassSessionRequest.php`, `web/routes/web.php`
  - `web/resources/js/pages/Schedule/Index.vue`, `web/database/seeders/DatabaseSeeder.php`
  - `web/tests/Support/ProtectedActions.php`, `web/tests/Feature/DatabaseSeederTest.php`
  - `README.md`, `DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/quality-document.md`, `PROGRESS.md` (generated)

## Decisions Made

- D-010:
  - the session row lock (`SELECT … FOR UPDATE`);
  - one active booking per customer as a unique index over a nullable `active`;
  - PostgreSQL tests in their own group, with racing processes that hold their transaction open.

## Blockers / Risks

- `npm run test` needs Docker (D-005, D-010); CI (feat-013) must provide it.
- **For feat-006:**
  - Release a booking by setting `active` to `NULL`, never `false` (the index depends on it).
  - Promotion from the waitlist must take the same session row lock as `BookSession`.
  - The promotion race test can reuse `bookInParallel()`.
- **From feat-004:**
  - Templates and sessions cannot be deleted yet.
  - The schedule has no date window or pagination.
  - The invitation mail is synchronous (feat-009).
- **From feat-003:**
  - The `TenantPasswordBrokerManager` override.
  - `/` on a tenant subdomain shows the central page.
  - Do not set `SESSION_DOMAIN`.
- **From feat-002:**
  - Tenant slugs are not validated.
  - A `TENANCY_DOMAIN` with a port is not rejected.
  - A job `middleware()` override without `RunInTenantContext` is not detected.
  - Cache keys and storage are not tenant-prefixed.
- Carried over:
  - Herd's `php.ini` Redis line may come back after a Herd update.
  - The `e2e` layer is still the harness's own suite until feat-013.
  - The `LICENSE` copyright line is still undecided.

## Next Session Startup

1. Read `AGENTS.md`.
2. Read `feature_list.json`, `PROGRESS.md`, `DECISIONS.md` and `docs/STANDARDS.md`.
3. Review this handoff.
4. Run `npm run setup:web` on a fresh clone. Start Docker, then run `./init.sh` and `npm run check` before editing.

## Recommended Next Step

- Start feat-006 (cancellation and waitlist): write `docs/sprints/feat-006.md` first, then `npm run activate -- feat-006`.
- The GitHub repository is private for now. Make it public when the project is ready to show: `gh repo edit maideps/studio-booking-saas --visibility public --accept-visibility-change-consequences`.
