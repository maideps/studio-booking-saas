# Sprint Contract

**Feature ID:** feat-006
**Feature Name:** Cancellation and waitlist
**Date:** 2026-10-20
**Estimated Duration:** 1 session

## What I Will Build

Customers cancel bookings up to a studio-defined time before class, join a first-in-first-out waitlist when a session is full, and are promoted automatically when a place opens. Every change to a session's places runs under the session row lock from D-010, so promotion can never race a new booking into overbooking.

- **Cancellation window.**
  - New studio setting `cancellation_window_hours` on `tenants`: 0 to 168, default 12, edited on the settings page.
  - A customer may cancel their own active booking until `starts_at` minus the window. After that the cancellation is refused with a message.
  - Cancelling releases the booking: `active` becomes `NULL` (D-010).
  - Staff and owners cancelling for a customer is out of scope.
- **Waitlist.** New tenant-owned `waitlist_entries` table:
  - columns `class_session_id`, `customer_id`, `active` (`true` or `NULL`, like bookings) and timestamps;
  - composite foreign keys (D-009);
  - a unique index for one active entry per customer and session.

  The order is the entry id: first in, first out.
  - **Joining** is refused when the session has started, when places are still free ("book instead"), when the customer already holds a booking, and when they are already waiting.
  - **Leaving** releases the entry.
- **Promotion.** `FillFromWaitlist` runs inside an already locked session. While the session has free places and active entries, it releases the first entry and books that customer. It runs in two places:
  - `CancelBooking` locks the session, releases the booking and promotes the next customer in the same transaction;
  - `ScheduleClassSession` locks the session while it changes the session, so a capacity increase promotes waiting customers too.

  The rule this keeps: a session never has a free place while someone is waiting. A direct booking therefore never jumps the queue.
- **Lock order.** Every action that changes places locks the session row first, then touches bookings and entries, so they cannot deadlock each other. The actions are `BookSession`, `CancelBooking`, `JoinWaitlist`, `LeaveWaitlist` and `ScheduleClassSession`.
- **HTTP and policies.**
  - `DELETE /bookings/{booking}` cancels a booking: the booking's own customer only (`BookingPolicy::cancel`).
  - `POST /schedule/{classSession}/waitlist` joins the waitlist: customers (`WaitlistEntryPolicy::create`).
  - `DELETE /waitlist/{waitlistEntry}` leaves it: the entry's own customer.
  - Refusals come back as the `booking` error on the schedule, as in feat-005.
- **Schedule page.** For the signed-in customer:
  - Cancel, with the last moment to cancel shown in studio time;
  - Join waitlist on a full session;
  - their position and a Leave button while waiting.
  Everyone sees how many people are waiting.

Out of scope:
- notifying the promoted customer (feat-009, queued mail);
- payments and refunds (feat-007, feat-008);
- staff cancelling for customers;
- the audit log (feat-010).

## How I Will Verify

- [ ] Layer 1 (static): `npm run lint` and `npm run typecheck` pass.
- [ ] Layer 2 (runtime): `npm run test` runs Pest on SQLite, the `postgres` group on PostgreSQL, then Vitest.
  - **Cancellation window:**
    - a cancel just inside the window succeeds and frees the place;
    - a cancel just outside it, and after the start, is refused;
    - the window follows the studio setting (0 hours allows cancelling until the start);
    - another customer's booking gets 403, and another studio's booking gets 404.
  - **Promotion order:**
    - with three customers waiting, each cancellation promotes the next one in join order;
    - a customer who left the waitlist is skipped;
    - raising the capacity by two promotes the first two.
  - **Waitlist rules:** joining a session with free places, a started session, a session already booked or already joined is refused. The database refuses a second active entry, and a cross-studio reference.
  - **Races (PostgreSQL, separate processes released together):**
    - One customer cancels while four others book the freed place. The first waitlisted customer gets the place, all four bookers are refused, and the session holds exactly its capacity.
    - Two customers cancel at the same moment with three waiting. The first two waiting customers are promoted in order and the third still waits, with no free place left behind.
    - One cancellation races four customers joining the waitlist. Whatever the order, the place is filled or nobody is left waiting.
    - The break-on-purpose check removes the session lock from `CancelBooking` and expects a race test to fail (see Amended During the Sprint).
  - **Role matrix:** cancel, join and leave join `ProtectedActions` (customer only; owner and staff 403; guest, foreign session and foreign owner covered).
- [ ] Layer 3 (system): `npm run check`, `npm run check-arch`, `npm run verify-feature -- feat-006`, `npm run vcr` and `npm run smoke` pass.

## What Success Looks Like

feat-006 is `passing` in `.harness/state/feat-006.json`. In the demo studio, a customer joins the waitlist of a full class, another customer cancels, and the first customer is booked in at once. Under parallel requests nobody jumps the queue and no place is double-filled or left empty while someone waits.

## Dependencies

- [x] Depends on: feat-005 (passing)
- [x] Blocked by: none

## Risks

- **`FOR UPDATE` re-checks in PostgreSQL.** In READ COMMITTED, a `SELECT … FOR UPDATE … LIMIT 1` that waited on a row PostgreSQL re-checks can return nothing instead of the next row. The promotion therefore relies on the session lock, not on locking waitlist rows, and the two-cancel race test covers it.
- **The invariant depends on every path promoting.** The path that frees a place, cancelling, and the one that adds places, raising the capacity, both call `FillFromWaitlist` under the lock. A future path that frees places (staff cancellation, deleting a booking) must do the same.
- **Promotion without a notification** means a promoted customer only sees it on the schedule until feat-009.

## Amended During the Sprint

- **The plan:** to prove the session lock in `CancelBooking` by removing it and expecting the two-cancel race to fail. **What happened:** that race kept passing. Two racing promotions meet on the waitlist entry's row lock, and the loser re-checks the customer and moves to the next waiting entry, so the outcome is still correct.
- **The case that does need the lock** is a cancellation racing customers who join the waitlist. Without the lock, a join can commit its entry after the cancel has looked for waiting customers, which leaves a free place while someone waits. A third race test asserts exactly that rule: either the place is filled or nobody waits. With the lock removed it failed on both runs, with one customer waiting beside a free place.
- **A false start:** the first version of that test passed with the lock removed. Its helper used PHP's `range(1, 0)`, which is `[1, 0]`, so it set up two waiting customers where none were meant. The helper now uses plain counting loops.

## Commit Plan

One commit: the feature code, migrations, tests, docs and harness state together, after the author approves it.

---

_Signed off by: Claude (Opus 5.5) on 2026-10-20_
