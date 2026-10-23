# Session Handoff

Fill this at session end for large sessions; keep it current otherwise.

## Current Objective

- Goal: build Studio Booking SaaS, a multi-tenant booking and membership platform (Laravel, Vue, PostgreSQL, Stripe test mode), as a portfolio project built with AI coding agents using ai-harness-framework, one feature at a time.
- Current status: feat-001 to feat-007 are `passing`; feat-007 is memberships and Stripe checkout. No feature is active. Next is feat-008 (Stripe webhooks and payment failures).
- Branch / commit: `main`, pushed to the private GitHub repository `maideps/studio-booking-saas` (`origin`). Commits so far: feat-001 `3014156`, feat-002 `8ade887`, feat-003 `5121d98`, feat-004 `ce4ea5a`, feat-005 `312585f`, feat-006 `673961a`, and feat-007 in the commit right after `673961a` (`feat(feat-007): ...`).

## Completed This Session

- [x] **Author's decision: tenant-owned billing tables and `stripe/stripe-php` instead of Cashier**, recorded as D-012. D-001 and the feat-007 behaviour text point to it.
- [x] feat-007 (details in `docs/ARCHITECTURE.md`, section Billing):
  - studio pricing on `tenants`;
  - `PaymentGateway`, implemented by `StripeGateway` (stripe-php Checkout Sessions, inline prices) and by `FakePaymentGateway` (tests and the local demo, refused in other environments, forced by `phpunit.xml`);
  - the tenant-owned `checkouts`, `memberships` and append-only `credit_entries`;
  - idempotent `FulfilCheckout`;
  - `Entitlements` (free studio, then membership, then credit, then refusal), applied in booking, waitlist joins, promotion, and refunds on cancellation;
  - the `/billing` page, checkout and return routes, and pricing in settings.
- [x] **Tests.**
  - Checkout, fulfilment, entitlement and settings.
  - The Stripe gateway against hand-written fixtures through a recorded stripe-php HTTP client.
  - PostgreSQL races: one credit and four parallel bookings; four parallel fulfilments.
  - The billing routes in the role matrix, and Vitest for the billing page.
  - 16 deliberate breaks were each caught.

## Verification Evidence

| Check | Command | Result | Notes |
|---|---|---|---|
| Full verification | `npm run check` | PASS | Pint, ESLint, Larastan, vue-tsc, Pest on SQLite (370 tests, 1,349 assertions), Pest on PostgreSQL (8 tests), Vitest (18 tests), Vite build and the harness's own tests all ran |
| Feature gate | `npm run verify-feature -- feat-007` | PASS | Evidence in `.harness/state/feat-007.json`. The SKIPs in Layer 3 are the harness's adoption-matrix toolchains and its example multi-repo entry, not product checks |
| Trail | `npm run vcr` | PASS | `.harness/trails/2026-10-20T19-27-07-768Z-vcr.json` |
| Compose stack | `npm run smoke` | PASS | The image built with stripe-php; PostgreSQL migrated with the billing migration |
| Architecture rules | `npm run check-arch` | PASS | |

## Files Changed

- New:
  - `web/app/Billing/` (gateway interface, Stripe and fake gateways, value objects, `Entitlements`), `web/config/billing.php`
  - `web/app/Actions/Billing/StartCheckout.php`, `FulfilCheckout.php`, `web/app/Exceptions/BillingRefused.php`
  - `web/app/Models/Checkout.php`, `Membership.php`, `CreditEntry.php` and their factories
  - `web/app/Enums/BookingPayment.php`, `CheckoutStatus.php`, `MembershipStatus.php`, `CreditReason.php`
  - `web/app/Policies/CheckoutPolicy.php`, `web/app/Http/Controllers/BillingController.php`, `web/resources/js/pages/Billing/Show.vue`
  - migration `2026_10_20_000006_create_billing_tables.php`
  - `web/tests/Feature/Billing/`, `web/tests/Concurrency/ParallelBillingTest.php`, `web/tests/Support/Billing/RecordedStripeHttpClient.php`, `web/tests/Fixtures/Stripe/`, `web/tests/js/BillingShow.test.ts`
  - `docs/sprints/feat-007.md`, `progress/008-2026-10-20.md`, `.harness/state/feat-007.json`, the vcr trail
- Changed:
  - `web/composer.json`, `web/composer.lock` (stripe/stripe-php)
  - `web/phpunit.xml`, `web/.env.example`, `docker-compose.yml`
  - `web/app/Models/Tenant.php`, `Booking.php`, `web/app/Providers/AppServiceProvider.php`, `web/app/Exceptions/BookingRefused.php`
  - `web/app/Actions/Bookings/BookSession.php`, `CancelBooking.php`, `FillFromWaitlist.php`, `JoinWaitlist.php`
  - `web/app/Http/Controllers/StudioSettingsController.php`, `web/app/Http/Requests/Studio/UpdateStudioSettingsRequest.php`, `web/app/Http/Middleware/HandleInertiaRequests.php`, `web/routes/web.php`
  - `web/resources/js/layouts/AppLayout.vue`, `pages/Settings/Edit.vue`, `types/inertia.d.ts`
  - `web/database/seeders/DatabaseSeeder.php`
  - `web/tests/Support/` (`ProtectedActions`, `StudioFixture`, `Concurrency/`), the settings, member and seeder tests
  - `feature_list.json` (feat-007 behaviour text), `README.md`, `DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/quality-document.md`, `PROGRESS.md` (generated)

## Decisions Made

- D-012:
  - billing tables are tenant-owned;
  - Stripe goes through a domain gateway (stripe-php), not Cashier;
  - fulfilment is idempotent;
  - entitlement is checked under the session lock, then the customer lock;
  - the fake gateway is allowed only in `local` and `testing`.

## Blockers / Risks

- **For feat-008:**
  - The webhook must verify the Stripe signature.
  - It must enter the tenant from the metadata (`tenant_id`) and record event ids for idempotency.
  - It must reuse `FulfilCheckout`, and extend memberships on renewal.
  - Grace and suspension states go into `MembershipStatus`.
  - Until then a membership lasts until the period end recorded at checkout.
- The Stripe gateway has only been tested against hand-written fixtures. A real test-mode checkout needs a `STRIPE_SECRET` test key from the author.
- A possible deadlock: two parallel promotions on different sessions promoting the same two customers in opposite order (only after a capacity increase). Not seen; not tested.
- Any new path that frees places must call `FillFromWaitlist` under the session lock (D-011).
- `npm run test` needs Docker (D-005, D-010).
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

- Start feat-008 (Stripe webhooks and payment failures): write `docs/sprints/feat-008.md` first, then `npm run activate -- feat-008`.
- The GitHub repository is private for now. Make it public when the project is ready to show: `gh repo edit maideps/studio-booking-saas --visibility public --accept-visibility-change-consequences`.
