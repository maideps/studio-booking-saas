# Session Handoff

Fill this at session end for large sessions; keep it current otherwise.

## Current Objective

- Goal: build Studio Booking SaaS, a multi-tenant booking and membership platform (Laravel, Vue, PostgreSQL, Stripe test mode), as a portfolio project built with AI coding agents using ai-harness-framework, one feature at a time.
- Current status: feat-001 to feat-004 are `passing`; feat-004 is studios, staff and classes. No feature is active. Next is feat-005 (bookings with capacity locking).
- Branch / commit: `main`, pushed to the private GitHub repository `maideps/studio-booking-saas` (`origin`). Commits so far: feat-001 `3014156`, feat-002 `8ade887`, feat-003 `5121d98`, and feat-004 in the commit right after `bd9e2fd` (`feat(feat-004): ...`).

## Completed This Session

- [x] feat-004, per the new D-009 (details in `docs/ARCHITECTURE.md`, section Studios, staff and classes):
  - studio settings (name, timezone, contact email), owner only, always on the current studio;
  - staff invitations: a hashed single-use link on the studio subdomain, valid 7 days, replaced on re-invite and revocable. Accepting works with a new account or a signed-in existing one, and never demotes an owner;
  - class templates (name unique per studio) and class sessions (template, instructor, time in the studio timezone stored as UTC, capacity);
  - composite (`tenant_id`, …) foreign keys for every reference between tenant-owned rows, enforced by a new architecture rule;
  - policies for each, and Inertia pages with navigation by role;
  - a seeder with two classes and a week of sessions.
- [x] Tests:
  - a role matrix: every protected route for each role, a guest, a replayed session of another studio, and `actingAs` an owner of another studio;
  - settings, invitation, class and session tests, including timezone conversion and database-level cross-studio refusals.
  20 deliberate breaks were each caught by a test.
- [x] Review pass (inline): an existing member is brought back to the invitation after logging in; accepting never demotes an owner; the composite-key rule is an architecture rule now.

## Verification Evidence

| Check | Command | Result | Notes |
|---|---|---|---|
| Full verification | `npm run check` | PASS | Pint, ESLint, Larastan, vue-tsc, Pest (262 tests, 962 assertions), Vitest (10 tests), Vite build and the harness's own tests all ran. The first run failed at typecheck; it passed after the fix |
| Feature gate | `npm run verify-feature -- feat-004` | PASS | Evidence in `.harness/state/feat-004.json`. The SKIPs in Layer 3 are the harness's adoption-matrix toolchains and its example multi-repo entry, not product checks |
| Trail | `npm run vcr` | PASS | `.harness/trails/2026-10-20T15-11-51-156Z-vcr.json` |
| Compose stack | `npm run smoke` | PASS | The app migrated PostgreSQL with the feat-004 migrations, composite foreign keys included |
| Architecture rules | `npm run check-arch` | PASS | |

## Files Changed

- New:
  - `web/app/Models/Invitation.php`, `ClassTemplate.php`, `ClassSession.php`, and their factories
  - `web/app/Policies/TenantPolicy.php`, `InvitationPolicy.php`, `ClassTemplatePolicy.php`, `ClassSessionPolicy.php`
  - `web/app/Actions/Members/InviteMember.php`, `AcceptInvitation.php`, `web/app/Actions/Classes/ScheduleClassSession.php`, `web/app/Notifications/StaffInvitation.php`
  - `web/app/Http/Controllers/StudioSettingsController.php`, `InvitationController.php`, `JoinController.php`, `ClassTemplateController.php`, `ClassSessionController.php`
  - `web/app/Http/Requests/Studio/`, `Members/InviteMemberRequest.php`, `Classes/`
  - four migrations `2026_10_20_000000` to `000003`
  - `web/resources/js/components/`, `pages/Settings/`, `pages/Classes/`, `pages/Schedule/`, `pages/Join.vue`
  - `web/tests/Feature/Authorization/`, `Studio/`, `Classes/`, `Members/InvitationTest.php`, `web/tests/Support/StudioFixture.php`, `web/tests/js/Join.test.ts`
  - `docs/sprints/feat-004.md`, `progress/005-2026-10-20.md`, `.harness/state/feat-004.json`, the vcr trail
- Changed:
  - `web/app/Models/Tenant.php`, `web/app/Http/Controllers/MemberController.php`, `web/app/Http/Middleware/HandleInertiaRequests.php`, `web/routes/web.php`
  - `web/database/seeders/DatabaseSeeder.php`
  - `web/resources/js/layouts/AppLayout.vue`, `pages/Members/Index.vue`, `types/inertia.d.ts`
  - `web/tests/Pest.php`, `web/tests/Support/ProtectedActions.php`, `web/tests/Support/TenancyArchitecture.php`, `web/tests/Datasets/Auth.php`, the Auth and Members tests, `TenancyArchitectureTest.php`, `DatabaseSeederTest.php`, `web/tests/js/MembersIndex.test.ts`
  - `README.md`, `DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/quality-document.md`, `PROGRESS.md` (generated)

## Decisions Made

- D-009:
  - composite foreign keys for references between tenant-owned rows;
  - invitations as hashed, single-use, studio-bound links;
  - settings on the `tenants` table;
  - times stored in UTC and shown in the studio timezone.

## Blockers / Risks

- **For feat-005 (a required step, D-005):** move the PHP tests that need PostgreSQL (row locks, parallel requests) to a PostgreSQL test database started by the test tooling.
- **From feat-004:**
  - Templates and sessions cannot be deleted yet. The template key would refuse deleting a template that has sessions.
  - The schedule has no date window or pagination.
  - The invitation mail is sent synchronously; feat-009 queues it.
  - Only the Join and Members pages have component tests.
- **From feat-003:**
  - `TenantPasswordBrokerManager` overrides Laravel's protected `createTokenRepository()`.
  - `/` on a tenant subdomain shows the central welcome page.
  - Do not set `SESSION_DOMAIN` to a parent domain.
- **From feat-002:**
  - Tenant slugs are not validated or reserved.
  - A `TENANCY_DOMAIN` with a port is not rejected.
  - A job that overrides `middleware()` without `RunInTenantContext` is not detected.
  - Cache keys and file storage are not tenant-prefixed.
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

- Start feat-005 (bookings with capacity locking):
  - Write `docs/sprints/feat-005.md` first, then `npm run activate -- feat-005`.
  - Start with the PostgreSQL test database (D-005).
  - The booking row references `class_sessions` and `users`, so it needs composite foreign keys (the architecture rule enforces it).
  - Add each new protected route to `tests/Support/ProtectedActions.php`.
- The GitHub repository is private for now. Make it public when the project is ready to show: `gh repo edit maideps/studio-booking-saas --visibility public --accept-visibility-change-consequences`.
