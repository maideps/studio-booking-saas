# Studio Booking SaaS

A multi-tenant booking and membership platform for small studios (yoga, fitness, barbers, physio). Each studio is a **tenant** with its own isolated workspace: staff publish classes and appointment slots, customers book them, and memberships are billed monthly. Built with Laravel, Vue 3 and PostgreSQL.

> **Portfolio project.** All data is synthetic, Stripe runs in test mode only, and emails are caught locally. It is built with AI coding agents using [ai-harness-framework](https://github.com/maideps/ai-harness-framework): one feature at a time, with layered verification and recorded evidence in this repository (`feature_list.json`, `progress/`, `DECISIONS.md`).

**Stack:** Laravel 13 (PHP 8.3+) · Vue 3 + TypeScript (Inertia) · PostgreSQL · Redis · Pest · Larastan · Docker Compose · GitHub Actions. Planned: Stripe (test mode, Cashier) in feat-007 and Playwright in feat-013.

**What it is meant to demonstrate:** tenant isolation that is proven by tests, booking without overbooking under concurrency, idempotent Stripe webhooks, role-based policies, queued and scheduled jobs, an append-only audit log, and a small versioned public API.

## Status

Early build. The project skeleton (feat-001) is in place: the Laravel app in [`web/`](web/), its tooling wired into the harness gates, and a Docker Compose stack. The tenancy foundation (feat-002) is built: tenants on subdomains, a tenant scope on every tenant-owned model, tenant-aware queued jobs, and tests that prove one studio cannot see another's data (see [Tenancy](docs/ARCHITECTURE.md#tenancy)). Authentication and roles (feat-003) are built: login, registration and password reset on each studio's subdomain, the roles owner, staff and customer, and policies that enforce them. Tests show that a session, reset token or login lockout from one studio has no effect in another (see [Authentication and roles](docs/ARCHITECTURE.md#authentication-and-roles)). Studios, staff and classes come next (feat-004). The 14-feature plan is in [`feature_list.json`](feature_list.json); the current state is in [`PROGRESS.md`](PROGRESS.md) and the hand-off for the next session is in [`session-handoff.md`](session-handoff.md).

## Quick Start

```bash
# 1. Install the Laravel app's dependencies (Composer, npm, a local .env and SQLite database)
npm run setup:web

# 2. Verify the environment is healthy
./init.sh

# 3. Run full verification (make and npm forms are 1:1 mirrors)
make check          # or: npm run check

# 4. Run the whole stack: app on http://localhost:8000, Mailpit on http://localhost:8025
#    (--build rebuilds the image so it picks up code changes; ports are bound to localhost only)
docker compose up --build

# 5. See all available commands
make help           # or: npm run help
```

`npm run smoke` builds the image, starts the stack as a throwaway Compose project on its own ports, checks that it serves the app, and removes it again.

To try sign-in, seed the demo studio (`docker compose exec app php artisan db:seed`) and open http://demo.localhost:8000/login. The seeder creates `owner@example.com`, `staff@example.com` and `customer@example.com`, all with the factory password `password`.

### Prerequisites

- **Node.js** ≥ 20.19 — the harness runtime and the front-end build (Vite 8); `.nvmrc` pins the version CI uses
- **PHP** ≥ 8.3 with Composer, and the `intl`, `pdo_sqlite` and `pdo_pgsql` extensions (Laravel Herd provides all of them on Windows and macOS)
- **Docker** with Compose — for `docker compose up` and `npm run smoke`
- **Make** — optional; `npm run <target>` mirrors every make target

## Verification Layers

| Layer | Command | What It Checks |
|-------|---------|----------------|
| 1 | `make lint` / `npm run lint` | Harness lint, Pint (PHP formatting, strict types), ESLint |
| 1b | `make typecheck` / `npm run typecheck` | Harness checks, Larastan (level 8), vue-tsc (strict) |
| 2 | `make test` / `npm run test` | Harness tests, Pest, Vitest |
| 3 | `make build` / `npm run build` | Harness build check, Vite production build |
| 3b | `make e2e` / `npm run e2e` | End-to-end tests (the harness's own for now; the product's Playwright suite arrives in feat-013) |

Each layer runs the harness's own check first, then the product tools in `web/` through the launchers in `tools/`. Layers that are not configured report **SKIP** and do not count as verified.

### Harness Commands

The complete command list is generated from the Makefile and npm scripts. See [Harness Command Reference](docs/HARNESS.md); regenerate it with `npm run harness-docs`.

## State Files

- `feature_list.json` — feature declarations: id, behavior, dependencies, verification layers
- `.harness/state/<id>.json` — that feature's mutable state (`state`, `evidence`); one file per feature
- `PROGRESS.md` — generated current-state dashboard and session index
- `progress/NNN-YYYY-MM-DD.md` — one session's notes
- `DECISIONS.md` — architectural decisions log
- `session-handoff.md` — multi-session handoff notes

## Documentation

- [Product Overview](docs/PRODUCT.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Observability](docs/OBSERVABILITY.md)
- [Tools & MCP](docs/TOOLS.md)
- [Usage Guide](docs/USAGE.md)
- [Quality Document](docs/quality-document.md)

## License

MIT — see [LICENSE](LICENSE).
