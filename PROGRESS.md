# Progress Log

Generated current-state dashboard and index of the per-session notes in `progress/`.

After adding or updating a session note, regenerate this file with `node scripts/framework-check.mjs progress-update`.

<!-- BEGIN GENERATED PROGRESS -->
## Current Verified State

- Project root: this repository
- Primary startup command: `./init.sh`
- Primary verification command: `make check` (make-free equivalent: `npm run check`)
- Active feature: none
- Passing features (7): feat-001 — Project skeleton and tooling, feat-002 — Tenancy foundation, feat-003 — Authentication and roles, feat-004 — Studios, staff and classes, feat-005 — Bookings with capacity locking, feat-006 — Cancellation and waitlist, feat-007 — Memberships and Stripe checkout
- Failing/blocked features: none
- Planned features (7): feat-008 — Stripe webhooks and payment failures, feat-009 — Notifications and scheduled jobs, feat-010 — Audit log, feat-011 — Public API, feat-012 — Customer and staff UI, feat-013 — End-to-end tests and CI, feat-014 — Demo data, README and release
- Last evidence: feat-007 — Memberships and Stripe checkout — All verification layers passed via node scripts/framework-check.mjs verify-feature (SKIP reported in: Layer 3: System confirmation) (2026-10-20T19:19:09.940Z)

## Session Index

- [Session 001 — 2026-10-17 — Scaffold and plan](progress/001-2026-10-17.md)
- [Session 002 — 2026-10-19 — feat-001 project skeleton and tooling](progress/002-2026-10-19.md)
- [Session 003 — 2026-10-19 — feat-002 tenancy foundation](progress/003-2026-10-19.md)
- [Session 004 — 2026-10-19 — feat-003 authentication and roles](progress/004-2026-10-19.md)
- [Session 005 — 2026-10-20 — feat-004 studios, staff and classes](progress/005-2026-10-20.md)
- [Session 006 — 2026-10-20 — feat-005 bookings with capacity locking](progress/006-2026-10-20.md)
- [Session 007 — 2026-10-20 — feat-006 cancellation and waitlist](progress/007-2026-10-20.md)
- [Session 008 — 2026-10-20 — feat-007 memberships and Stripe checkout](progress/008-2026-10-20.md)
<!-- END GENERATED PROGRESS -->
