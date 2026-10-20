# Sprint Contract

**Feature ID:** feat-004
**Feature Name:** Studios, staff and classes
**Date:** 2026-10-20
**Estimated Duration:** 1 session

## What I Will Build

Studio settings, staff invitations, class templates and scheduled class sessions. All of it is inside the tenant (D-002, D-007), and the roles and policies come from feat-003 (D-008).

- **Studio settings** (owner only).
  - New columns on `tenants`: `timezone` (default `UTC`) and `contact_email`.
  - The settings page edits the studio name, timezone and contact email.
  - The controller always edits the current tenant. A tenant id in the request is ignored.
- **Staff invitations** (owner only).
  - New tenant-owned `invitations` table: `email`, `role` (staff or owner), a hashed `token`, `invited_by_id` and `expires_at` (7 days). There is one pending invitation per email and tenant, and a new invitation replaces the old one.
  - The invitee gets an email (not queued yet; that is feat-009) with a link on the studio's subdomain, `/join/{token}`.
  - **Accepting:**
    - With no account in this studio, the invitee chooses a name and password. The account is created with the invited role, and the invitee is signed in.
    - With an existing account (for example a customer), the invitee must be signed in as that account. The role then changes to the invited one.
    - A user signed in under another email cannot accept.
    - The invitation is deleted on acceptance.
  - Owners can revoke a pending invitation. The members page lists pending invitations.
  - Inviting an email that already belongs to an owner or staff member of the studio is refused.
- **Class templates.** New tenant-owned `class_templates` table: `name` (unique per tenant), `description`, `duration_minutes` and a default `capacity`.
  - Owners and staff create and edit them.
  - Every member can list them.
- **Class sessions.** New tenant-owned `class_sessions` table: `class_template_id`, `instructor_id`, `starts_at`, `ends_at` (start plus the template duration) and `capacity`.
  - Owners and staff schedule and edit them. The instructor must be an owner or staff member of the same studio.
  - Every member can list upcoming sessions.
  - Times are entered and shown in the studio timezone and stored in UTC.
- **Cross-tenant references in the database.** Each reference from a tenant-owned row to another tenant-owned row gets a composite foreign key. For example, (`tenant_id`, `class_template_id`) references `class_templates` (`tenant_id`, `id`). The database itself then refuses a session that points at another studio's template or instructor, even when validation is skipped. `users` and `class_templates` get a unique (`tenant_id`, `id`) index for this.
- **Policies.**
  - `TenantPolicy::update`: owner.
  - `InvitationPolicy`: `create` and `delete`, owner.
  - `ClassTemplatePolicy` and `ClassSessionPolicy`: `viewAny` for every member; `create` and `update` for owners and staff.
  - `Gate::before` (D-008) still denies users of other tenants.
- **Validation.** References use `TenantRule::exists`, and the template name uses `TenantRule::unique`, as the feat-003 architecture rule requires.
- **Front end.** Minimal Inertia pages: Settings, Classes (list and form), Schedule (list and form), Join (accept an invitation), and an invite form plus pending invitations on Members. The navigation shows only what the signed-in role may use. The full UI stays in feat-012.
- **Seeder.** The demo studio gets two class templates and a few sessions in the coming week.

Out of scope:
- bookings and capacity enforcement (feat-005);
- cancellation and the waitlist (feat-006);
- queued mail (feat-009);
- deleting templates or sessions;
- the PostgreSQL test database (a required step of feat-005, D-005).

## How I Will Verify

- [ ] Layer 1 (static): `npm run lint` (Pint, ESLint) and `npm run typecheck` (Larastan level 8, vue-tsc) pass.
- [ ] Layer 2 (runtime): `npm run test` runs Pest on SQLite in memory (D-005) and Vitest.
  - **The role matrix.** `Tests\Support\ProtectedActions` lists every route that needs a signed-in user, with the roles allowed and a valid payload.
    - Each action is run for owner, staff and customer: allowed roles succeed, the others get 403.
    - Each action is also run for a guest (redirect to login) and for a replayed tenant-A session on tenant B (guest).
    - Actions behind a policy are also run with `actingAs` a user of another tenant (403).
    - A test fails when a route with `auth` middleware is missing from the list.
  - **Isolation:**
    - another studio's template, session or invitation is a 404 through route model binding;
    - validation rejects another studio's template or instructor, and a customer as instructor;
    - the composite foreign keys reject a cross-tenant reference written without validation;
    - the settings form only ever changes the current tenant.
  - **Invitations:**
    - the link points to the studio's subdomain;
    - accepting with a new account, and with an existing customer account (sign-in required);
    - a wrong, expired or other-studio token is refused;
    - a different signed-in user cannot accept;
    - re-inviting replaces the old token;
    - an existing staff email is refused.
  - **Time:**
    - a session entered in the studio timezone is stored in UTC and shown back in local time;
    - `ends_at` follows the template duration.
- [ ] Layer 3 (system): `npm run check` and `npm run check-arch` pass. Then `npm run verify-feature -- feat-004` and `npm run vcr`. `npm run smoke` runs the migrations on PostgreSQL.

## What Success Looks Like

feat-004 is `passing` in `.harness/state/feat-004.json`. In the demo studio:
- the owner invites a staff member, who joins through the emailed link;
- owners and staff create class templates and schedule sessions;
- customers see the schedule.
No page, form or foreign key lets one studio read or point at another studio's classes or staff.

## Dependencies

- [x] Depends on: feat-003 (passing)
- [x] Blocked by: none

## Risks

- **Composite foreign keys need care.** The parent table needs a unique (`tenant_id`, `id`) index, and SQLite only enforces foreign keys while `foreign_key_constraints` is on (it is, by default). The smoke test checks the migrations on PostgreSQL.
- **Settings write the central `tenants` table** from a tenant request. The controller must never take the tenant from the request. A test posts another tenant's id and checks that it is ignored.
- **Invitation tokens are stored as SHA-256 hashes,** so a token can be found by lookup, unlike bcrypt. The tokens are 40 random characters, so a hash leak does not let anyone guess them.
- **Timezones.** Conversion is done on the server with Carbon. The SQLite tests cannot show PostgreSQL `timestamptz` behaviour, so the columns are plain UTC timestamps.

## Commit Plan

One commit: the feature code, migrations, tests, docs and harness state together, after the author approves it.

---

_Signed off by: Claude (Opus 5.5) on 2026-10-20_
