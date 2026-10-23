# Sprint Contract

**Feature ID:** feat-007
**Feature Name:** Memberships and Stripe checkout
**Date:** 2026-10-20
**Estimated Duration:** 1 session

## What I Will Build

Studios sell one monthly membership and one class pack (the product non-goals allow no more). Customers buy them through Stripe Checkout in test mode, and an entitlement rule decides whether a customer may book. Stripe sits behind a domain-owned interface (D-004).

The author chose tenant-owned billing tables and `stripe/stripe-php` over Laravel Cashier. Cashier's tables are not tenant-owned, its webhook controller looks users up with no tenant, and its `Billable` assumes one Stripe customer per email. This is recorded as D-012, which replaces the Cashier part of D-001.

- **Pricing.** New studio settings on `tenants`, edited by the owner on the settings page:
  - `currency` (default `eur`);
  - `membership_price_cents` (monthly; empty means no membership is offered);
  - `class_pack_size` and `class_pack_price_cents` (empty means no pack).

  A studio that offers neither stays free to book, as before.
- **Payment gateway.** `App\Billing\PaymentGateway` has two operations:
  - start a hosted checkout and get its URL;
  - read a checkout's result: paid or not, and for a membership the subscription id and the end of the paid period.

  There are two implementations:
  - `StripeGateway` uses `stripe/stripe-php` and Checkout Sessions with inline prices: `subscription` mode for the monthly membership, `payment` mode for a pack.
  - `FakePaymentGateway` keeps sessions in memory, makes no network calls, and lets a test mark a session paid.

  `config/billing.php` chooses the gateway. The fake is allowed only in the `local` and `testing` environments, and production without a Stripe key fails loudly. The test suite always binds the fake.
- **Tables.** All tenant-owned, with composite foreign keys (D-009):
  - `checkouts`: customer, kind (`membership` or `class_pack`), amount, currency, credits, gateway session id (unique), status (`pending` or `completed`), completed time.
  - `memberships`: one row per customer; status (`active` or `cancelled`), period end, gateway subscription id (unique), the checkout that started it.
  - `credit_entries`: an append-only class-pack ledger. Each entry has a change (+N purchase, −1 booking, +1 refund), a reason, and the checkout or booking it belongs to. The balance is the sum. The checkout reference is unique, so a purchase can be credited only once.
  - `bookings.paid_with` (`membership`, `credit` or `free`) records how each booking was covered.
- **Checkout flow** (customers).
  - `POST /billing/checkout/{kind}` creates a pending checkout, starts it at the gateway, and redirects to the hosted page. The success URL is on the studio's subdomain.
  - `GET /billing/checkouts/{checkout}/return` asks the gateway for the result and fulfils a paid checkout:
    - a membership becomes active with its period end;
    - a pack adds its credits.
  - `FulfilCheckout` is idempotent: it locks the checkout row, does nothing for a completed checkout, and the unique indexes back it up. The feat-008 webhooks will reuse it.
  - `GET /billing` shows the offers, the customer's membership and credit balance, and the buy buttons.
- **Entitlement.** Checked by `BookSession` under the session lock. The customer row is locked after the session row (one lock order), so parallel bookings cannot spend the same credit twice.
  - A studio with no paid offers: the booking is free.
  - Otherwise an active membership covers the booking.
  - Otherwise one credit is spent.
  - Otherwise the booking is refused: "Buy a membership or a class pack to book."
- **The other paths follow the same rule.**
  - `JoinWaitlist` refuses a customer with no entitlement.
  - `FillFromWaitlist` charges the promoted customer the same way. A waiting customer who is no longer entitled loses their place in line, and the next customer is tried.
  - `CancelBooking` refunds a credit to a booking that spent one.

Out of scope:
- webhooks, renewals and payment failures (feat-008: the membership period is set at checkout and not yet renewed);
- receipts and confirmation mail (feat-009);
- refunds of money;
- Stripe Connect payouts to studios (one platform account).

## How I Will Verify

- [ ] Layer 1 (static): `npm run lint` and `npm run typecheck` pass.
- [ ] Layer 2 (runtime): `npm run test`: Pest on SQLite, the `postgres` group on PostgreSQL, Vitest. **No test calls the network.** The suite binds the fake gateway, and a test checks that binding. The Stripe gateway is tested against recorded JSON responses through a fake stripe-php HTTP client.
  - **Subscribe:**
    - checkout creation (amount, mode, studio success URL);
    - a paid return activates the membership;
    - an unpaid return changes nothing;
    - a second return, or another customer's checkout, changes nothing.
  - **Entitlement checks:**
    - a free studio books without payment;
    - a paid studio refuses a customer with nothing;
    - an active membership books without spending credits;
    - a cancelled membership is refused;
    - joining a waitlist needs an entitlement.
  - **Class-pack credit use:**
    - a pack adds N credits;
    - each booking spends one;
    - a cancellation inside the window refunds it;
    - the last credit is spent once, then the next booking is refused;
    - a promoted customer pays with a credit, and a waiting customer with none is skipped.
  - **Races (PostgreSQL):**
    - one customer with one credit books four sessions at once: exactly one booking, balance 0;
    - the same checkout is fulfilled by four processes at once: exactly one credit purchase.
  - **Isolation and roles:** the billing routes join the role matrix (customers only). Another studio's checkout is a 404, and the pricing settings are owner only.
- [ ] Layer 3 (system): `npm run check`, `npm run check-arch`, `npm run verify-feature -- feat-007`, `npm run vcr` and `npm run smoke` pass.

## What Success Looks Like

feat-007 is `passing` in `.harness/state/feat-007.json`.
- In the demo studio (which uses the fake gateway locally), a customer buys a class pack, books with a credit, cancels and gets the credit back.
- With a real Stripe test key the same flow opens Stripe Checkout.
- Nothing lets a customer book a paid studio's class without paying, or spend one credit twice.

## Dependencies

- [x] Depends on: feat-006 (passing)
- [x] Blocked by: none. `composer require stripe/stripe-php` needs network access to Packagist, once.

## Risks

- **No renewal yet.** Without webhooks (feat-008), a membership is active until the period end recorded at checkout. Until feat-008 lands, renewal and cancellation at Stripe are not reflected.
- **Fulfilment on return** trusts the gateway's answer for the session id stored on our checkout row, never the query string alone, and only the checkout's own customer can trigger it.
- **Stripe API changes.** Stripe's API version moves fields; for example, the subscription period end is moving to subscription items. The gateway reads both places, and recorded fixtures pin the expected shapes.
- **The fake gateway in local development** must never run in production. It is refused outside `local` and `testing`.

## Commit Plan

One commit: the dependency, feature code, migrations, tests, docs and harness state together, after the author approves it.

---

_Signed off by: Claude (Opus 5.5) on 2026-10-20_
