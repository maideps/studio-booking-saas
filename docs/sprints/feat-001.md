# Sprint Contract

**Feature ID:** feat-001
**Feature Name:** Project skeleton and tooling
**Date:** 2026-10-19
**Estimated Duration:** 1 session

## What I Will Build

A Laravel application in `web/` (D-003) with Vue 3 + TypeScript through Inertia, and the tools from `docs/STANDARDS.md`:

- PHP: Pest for tests, Larastan (level 8) for types, Pint for formatting with `declare(strict_types=1)` enforced in every PHP file.
- Front end: ESLint (Vue + TypeScript), vue-tsc in strict mode, Vitest with Vue Test Utils, Vite build.
- A `Welcome` Inertia page served from `/`, and Laravel's `/up` health route.
- Docker Compose with the app, a queue worker, the scheduler, PostgreSQL, Redis and Mailpit. One image built from `docker/app/Dockerfile` serves all three PHP roles.
- Launchers in `tools/` (`php.mjs`, `web.mjs`) that run the PHP and Node tooling from the repository root on Windows and Linux, wired into the root `lint`, `typecheck`, `test` and `build` scripts after the harness's own layer.
- `npm run setup:web` to install `web/` dependencies, and `npm run smoke` to bring the Compose stack up, check that it serves the app, and tear it down.

No tenancy, auth or domain code; those start in feat-002.

## How I Will Verify

- [ ] Layer 1 (static): `npm run lint` runs the harness lint, `pint --test` and ESLint; `npm run typecheck` runs Larastan and vue-tsc
- [ ] Layer 2 (runtime): `npm run test` runs the harness tests, Pest (`/up` returns 200, `/` renders the `Welcome` Inertia page) and Vitest (the `Welcome` page renders)
- [ ] Layer 3 (system): `npm run check` passes end to end; `npm run smoke` shows `docker compose up` serving `/up` and `/`; `npm run verify-feature -- feat-001` passes

## What Success Looks Like

A fresh clone runs `npm run setup:web`, then `npm run check`, and every product layer runs real tools instead of reporting SKIP. `docker compose up` starts the stack and the app answers on `http://localhost:8000`.

## Dependencies

- [x] Depends on: none
- [x] Blocked by: none. PHP 8.3.8 and Composer 2.7.7 come from Laravel Herd; Docker Desktop 29.3.1 is running.

## Risks

- PHP and Composer are `.bat` shims from Herd on Windows, so the launchers must start them through a shell there.
- PHP tests use SQLite in memory for now so they run without Docker. The capacity tests in feat-005 need PostgreSQL row locks, so the test database has to move to PostgreSQL by then.
- The `e2e` layer still tests the harness, not the product, until feat-013.
- The Docker image needs an `APP_KEY` without one being committed. The entrypoint generates it once into a shared volume.

## Commit Plan

One commit: the `web/` skeleton, `docker/`, `docker-compose.yml`, `tools/`, root script wiring, CI setup for PHP, docs and harness state together.

---

_Signed off by: Claude (Opus 5.5) on 2026-10-19_
