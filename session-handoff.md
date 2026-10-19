# Session Handoff

Fill this at session end for large sessions; keep it current otherwise.

## Current Objective

- Goal: build Studio Booking SaaS, a multi-tenant booking and membership platform (Laravel, Vue, PostgreSQL, Stripe test mode), as a portfolio project built with AI coding agents using ai-harness-framework, one feature at a time.
- Current status: feat-001 (project skeleton and tooling) is `passing`. No feature is active. Next is feat-002 (tenancy foundation).
- Branch / commit: `main`, local repository only, with no GitHub remote yet. The session 002 work is **not committed yet**; it waits for the author's go-ahead.

## Completed This Session

- [x] Confirmed the harness is the latest ai-harness-framework (`b2bf54d`, v0.5.0); no upgrade needed.
- [x] feat-001:
  - Laravel 13 app in `web/`: Inertia v3, Vue 3 and TypeScript; Pest, Larastan level 8, Pint with strict types enforced, ESLint, vue-tsc and Vitest.
  - `tools/` launchers wired into the root gates; `npm run setup:web` and `npm run smoke`.
  - Docker Compose stack (app, worker, scheduler, PostgreSQL, Redis, Mailpit).
  - CI installs PHP before the gates.
- [x] Independent review pass; fixes applied (see `progress/002-2026-10-19.md`).
- [x] Decisions D-005 (SQLite tests until feat-005) and D-006 (one app image, runtime `APP_KEY`).

## Verification Evidence

| Check | Command | Result | Notes |
|---|---|---|---|
| Full verification | `npm run check` | PASS | Pint, ESLint, Larastan, vue-tsc, Pest (4 tests), Vitest (1 test), Vite build and the harness's own tests all ran |
| Feature gate | `npm run verify-feature -- feat-001` | PASS | Evidence in `.harness/state/feat-001.json`. The SKIPs in Layer 3 are the harness's adoption-matrix toolchains (Python, Go, Rust) and its example multi-repo entry, not product checks |
| Compose stack | `npm run smoke` | PASS | Image built; `/up`, the `Welcome` page and its script served; all six containers running with zero restarts; torn down |
| Architecture rules | `npm run check-arch` | PASS | |

## Files Changed

- New:
  - `web/` (Laravel app), `tools/`, `docker/app/`, `docker-compose.yml`
  - `docs/sprints/feat-001.md`, `progress/002-2026-10-19.md`, `.harness/state/feat-001.json`
- Changed:
  - root `package.json` scripts, `.github/workflows/ci.yml`
  - `README.md`, `DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/quality-document.md`
  - `docs/HARNESS.md` and `PROGRESS.md` (both generated)

## Decisions Made

- D-005: PHP tests run on SQLite in memory until feat-005 needs PostgreSQL row locks.
- D-006: one Docker image for the web app, worker and scheduler. `APP_KEY` is generated into a shared volume when none is set. Ports are bound to `127.0.0.1`.

## Blockers / Risks

- **Before feat-005:** move the PHP tests (at least the capacity tests) to a PostgreSQL test database (D-005).
- **Herd's `php.ini`:** the broken `php_redis-8.3.dll` line was commented out on the author's machine (backup `php.ini.bak-2026-10-19`). A Herd update may bring it back. Local PHP has no Redis extension; the Docker image does.
- **The `e2e` layer** still runs the harness's own suite. A green `npm run check` is not product end-to-end evidence until feat-013 adds Playwright.
- **`LICENSE`** is a harness CORE file and `harness-upgrade` resets it to the framework's copyright line. Still undecided.
- **Not done here:** CI dependency caching (left for feat-013); exact pins for `pecl redis` and the Mailpit image.

## Next Session Startup

1. Read `AGENTS.md`.
2. Read `feature_list.json`, `PROGRESS.md`, `DECISIONS.md` and `docs/STANDARDS.md`.
3. Review this handoff.
4. Run `npm run setup:web` on a fresh clone, then `./init.sh` and `npm run check` before editing.

## Recommended Next Step

- Start feat-002 (tenancy foundation):
  - Write `docs/sprints/feat-002.md` first, then `npm run activate -- feat-002`.
  - Build the `Tenant` model, subdomain resolution, the `BelongsToTenant` trait with its global scope, tenant context in queued jobs, and a two-tenant test helper (D-002).
  - Add a Pest architecture test that fails if a tenant-owned model lacks the trait or bypasses the scope outside the one named helper.
- Create the GitHub repository when ready: `gh repo create maideps/studio-booking-saas --public --source . --push`.
