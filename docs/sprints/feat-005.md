# Sprint Contract

**Feature ID:** feat-005
**Feature Name:** Bookings with capacity locking
**Date:** 2026-10-20
**Estimated Duration:** 1 session

## What I Will Build

Customers book places in scheduled class sessions. A session never holds more active bookings than its capacity, and a customer holds at most one active booking per session. Both rules hold under parallel requests.

- **PostgreSQL test database** (a required step of feat-005, D-005).
  - `tools/test-postgres.mjs` starts a throwaway PostgreSQL 17 container (the same image as the Compose stack) on its own port, runs the Pest tests in the `postgres` group against it, and removes it.
  - `npm run test` runs it after the SQLite suite. It fails with a clear message when Docker is not running, because the concurrency tests are part of the feature's proof.
  - The tests in the `postgres` group live in `web/tests/Concurrency/`, migrate a fresh database for each test and commit their data, so separate PHP processes can see it. On SQLite they are skipped.
- **Bookings.** A new tenant-owned `bookings` table:
  - columns `class_session_id`, `customer_id`, `active` and timestamps;
  - composite foreign keys (`tenant_id`, …) to `class_sessions` and `users` (D-009). `class_sessions` gets the unique (`tenant_id`, `id`) index for this;
  - **one active booking per customer and session**: `active` is `true` while a booking holds a place and `NULL` once released (feat-006 cancels this way). A unique index on (`tenant_id`, `class_session_id`, `customer_id`, `active`) treats NULLs as distinct in both PostgreSQL and SQLite. Released bookings therefore never block rebooking, while two active ones are refused by the database.
- **`BookSession` action.** In one transaction:
  1. lock the session row (`SELECT … FOR UPDATE`);
  2. refuse a session that has started;
  3. refuse a second active booking by the same customer;
  4. count the active bookings and refuse when the session is full;
  5. insert the booking.

  The row lock serializes bookings for the same session, so the count is never stale. A unique-index violation from a racing duplicate is turned into the same "already booked" answer.
- **HTTP.** `POST /schedule/{classSession}/bookings` books the signed-in customer.
  - `BookingPolicy::create` allows customers only; booking on behalf of a customer is later.
  - Errors come back as a validation error on the schedule page: full, already booked, or started.
  - The schedule shows places left per session, whether the signed-in customer has booked it, and a Book button.
- **Capacity edits.** Rescheduling a session cannot set its capacity below its active bookings.

Out of scope:
- cancellation, the waitlist and promotion (feat-006);
- payment entitlements (feat-007);
- confirmation mail (feat-009);
- staff booking on behalf of customers;
- attendee lists.

## How I Will Verify

- [ ] Layer 1 (static): `npm run lint` and `npm run typecheck` pass.
- [ ] Layer 2 (runtime): `npm run test` runs Pest on SQLite, then the `postgres` group on a real PostgreSQL, then Vitest.
  - **Concurrency (PostgreSQL, separate PHP processes started together):**
    - Eight customers book the last place of a session at the same moment. Exactly one succeeds, seven are told it is full, and the session ends with exactly its capacity.
    - One customer books the same session from five processes at once. Exactly one booking exists.
    - Each attempt holds its transaction open briefly after booking, which widens the race window. Without the row lock the first test must fail: the break-on-purpose check removes the lock and expects a failure.
  - **Behaviour (SQLite):**
    - booking succeeds and shows on the schedule;
    - a full, started or already-booked session is refused with a message;
    - a released booking does not block rebooking;
    - another studio's session is a 404;
    - capacity cannot drop below the active bookings;
    - the database refuses a second active booking and a cross-studio reference.
  - **Role matrix:** the booking route joins `ProtectedActions`.
    - A customer can book; owner and staff get 403.
    - A guest is redirected to login.
    - A replayed session of another studio is a guest.
    - An owner of another studio gets 403.
- [ ] Layer 3 (system): `npm run check` and `npm run check-arch` pass. Then `npm run verify-feature -- feat-005`, `npm run vcr` and `npm run smoke`.

## What Success Looks Like

feat-005 is `passing` in `.harness/state/feat-005.json`. In the demo studio a customer books a class from the schedule and sees the places left go down. `npm run test` proves on PostgreSQL that parallel bookings never overbook a session and never double-book a customer.

## Dependencies

- [x] Depends on: feat-004 (passing)
- [x] Blocked by: none

## Risks

- **`npm run test` now needs Docker.** That is the trade-off D-005 recorded. The launcher says so plainly when Docker is missing, and CI (feat-013) will provide it.
- **Process start-up on Windows is slow** (a Laravel boot per process). The processes wait for a shared start time before booking, and each one holds its transaction open for a moment, so the attempts overlap whatever the start-up time.
- **SQLite ignores `FOR UPDATE`.** The SQLite tests check the rules; only the PostgreSQL tests check the locking.
- **The nullable-`active` unique index** relies on NULLs being distinct in unique indexes. That is true for PostgreSQL (without `NULLS NOT DISTINCT`) and SQLite, and a test checks it on both.

## Commit Plan

One commit: the feature code, test tooling, migrations, tests, docs and harness state together, after the author approves it.

---

_Signed off by: Claude (Opus 5.5) on 2026-10-20_
