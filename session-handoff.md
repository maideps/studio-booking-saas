# Session Handoff

Fill this at session end for large sessions; keep it current otherwise.

## Current Objective

- Goal: build Studio Booking SaaS, a multi-tenant booking and membership platform (Laravel, Vue, PostgreSQL, Stripe test mode), as a portfolio project built with AI coding agents using ai-harness-framework, one feature at a time.
- Current status: feat-001 to feat-006 are `passing`; feat-006 is cancellation and waitlist. No feature is active. Next is feat-007 (memberships and Stripe checkout).
- Branch / commit: `main`, pushed to the private GitHub repository `maideps/studio-booking-saas` (`origin`). Commits so far: feat-001 `3014156`, feat-002 `8ade887`, feat-003 `5121d98`, feat-004 `ce4ea5a`, feat-005 `312585f`, and feat-006 in the commit right after `312585f` (`feat(feat-006): ...`).

## Completed This Session

- [x] feat-006, per the new D-011 (details in `docs/ARCHITECTURE.md`, section Cancellation and waitlist).
  - **Cancellation** until the start minus the studio's `cancellation_window_hours` (default 12, on the settings page).
  - **A first-in-first-out waitlist** (`waitlist_entries`), which a customer can join only when the session is full, and leave.
  - **Promotion** (`FillFromWaitlist`) inside the cancelling or rescheduling transaction, so a session never has a free place while someone waits.
  - **Locking.** Every change to a session's places locks the session row first (`ClassSession::lockedForChange()`).
  - **Schedule page:** Cancel with the deadline, Join waitlist, the position in line, and Leave.
- [x] Tests.
  - SQLite: window boundaries, promotion order, leaving, a capacity increase, join rules, isolation, database constraints.
  - PostgreSQL races:
    - a cancellation against new bookings;
    - two cancellations at once;
    - a cancellation against waitlist joins. It fails without the cancel lock.
  - Cancel, join and leave in the role matrix, and Vitest for the schedule.
  - 13 deliberate breaks were each caught.
- [x] The sprint contract was amended. The two-cancellation race does not need the session lock, and the cancellation-against-joins race does. A `range(1, 0)` bug in a test helper was found and fixed.

## Verification Evidence

| Check | Command | Result | Notes |
|---|---|---|---|
| Full verification | `npm run check` | PASS | Pint, ESLint, Larastan, vue-tsc, Pest on SQLite (320 tests, 1,184 assertions), Pest on PostgreSQL (6 tests), Vitest (15 tests), Vite build and the harness's own tests all ran |
| Feature gate | `npm run verify-feature -- feat-006` | PASS | Evidence in `.harness/state/feat-006.json`. The SKIPs in Layer 3 are the harness's adoption-matrix toolchains and its example multi-repo entry, not product checks |
| Trail | `npm run vcr` | PASS | `.harness/trails/2026-10-20T18-14-07-221Z-vcr.json` |
| Compose stack | `npm run smoke` | PASS | The app migrated PostgreSQL with the waitlist migration |
| Architecture rules | `npm run check-arch` | PASS | |

## Files Changed

- New:
  - `web/app/Models/WaitlistEntry.php`, `web/database/factories/WaitlistEntryFactory.php`, migration `2026_10_20_000005_create_waitlist_entries_table.php`
  - `web/app/Actions/Bookings/CancelBooking.php`, `FillFromWaitlist.php`, `JoinWaitlist.php`, `LeaveWaitlist.php`
  - `web/app/Policies/WaitlistEntryPolicy.php`, `web/app/Http/Controllers/WaitlistController.php`
  - `web/tests/Feature/Bookings/CancellationTest.php`, `WaitlistTest.php`, `web/tests/Concurrency/ParallelWaitlistTest.php`, `web/tests/Support/Concurrency/ParallelAttempts.php`
  - `docs/sprints/feat-006.md`, `progress/007-2026-10-20.md`, `.harness/state/feat-006.json`, the vcr trail
- Changed:
  - `web/app/Models/Tenant.php`, `ClassSession.php`, `web/app/Exceptions/BookingRefused.php`
  - `web/app/Actions/Bookings/BookSession.php`, `web/app/Actions/Classes/ScheduleClassSession.php`, `web/app/Policies/BookingPolicy.php`
  - `web/app/Http/Controllers/BookingController.php`, `ClassSessionController.php`, `StudioSettingsController.php`, `web/app/Http/Requests/Studio/UpdateStudioSettingsRequest.php`, `web/routes/web.php`
  - `web/resources/js/pages/Schedule/Index.vue`, `Settings/Edit.vue`
  - `web/tests/Support/ProtectedActions.php`, `StudioFixture.php`, `Concurrency/attempt-booking.php`, `web/tests/Concurrency/ParallelBookingTest.php`, the Auth, Authorization and Studio tests, `web/tests/js/ScheduleIndex.test.ts`
  - `README.md`, `DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/quality-document.md`, `PROGRESS.md` (generated)

## Decisions Made

- D-011:
  - a session never has a free place while someone waits;
  - promotion runs inside the transaction that frees or adds places;
  - one session lock for every change to places;
  - the cancellation window is a studio setting.

## Blockers / Risks

- **For feat-007 and later:**
  - Any new path that frees places (staff cancellation, deleting a session, a suspended membership releasing bookings) must call `FillFromWaitlist` under the session lock (D-011).
  - Entitlement checks before booking will go into `BookSession`, under the same lock.
- A promoted customer is not notified until feat-009.
- `npm run test` needs Docker (D-005, D-010); CI (feat-013) must provide it.
- **From earlier features:**
  - Templates and sessions cannot be deleted.
  - The schedule has no date window.
  - The invitation mail is synchronous.
  - The `TenantPasswordBrokerManager` override.
  - `/` on a tenant subdomain shows the central page.
  - Do not set `SESSION_DOMAIN`.
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

- Start feat-007 (memberships and Stripe checkout): write `docs/sprints/feat-007.md` first, then `npm run activate -- feat-007`.
  - Stripe sits behind a domain-owned interface with a fake gateway in tests (D-004).
  - Entitlement checks go into `BookSession`.
- The GitHub repository is private for now. Make it public when the project is ready to show: `gh repo edit maideps/studio-booking-saas --visibility public --accept-visibility-change-consequences`.
