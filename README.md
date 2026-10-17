# Studio Booking SaaS

A multi-tenant booking and membership platform for small studios (yoga, fitness, barbers, physio). Each studio is a **tenant** with its own isolated workspace: staff publish classes and appointment slots, customers book them, and memberships are billed monthly. Built with Laravel, Vue 3 and PostgreSQL.

> **Portfolio project.** All data is synthetic, Stripe runs in test mode only, and emails are caught locally. It is built with AI coding agents using [ai-harness-framework](https://github.com/maideps/ai-harness-framework): one feature at a time, with layered verification and recorded evidence in this repository (`feature_list.json`, `progress/`, `DECISIONS.md`).

**Stack (planned):** Laravel (PHP 8.3+) · Vue 3 + TypeScript (Inertia) · PostgreSQL · Redis · Stripe (test mode, Cashier) · Pest · Larastan · Playwright · Docker Compose · GitHub Actions

**What it is meant to demonstrate:** tenant isolation that is proven by tests, booking without overbooking under concurrency, idempotent Stripe webhooks, role-based policies, queued and scheduled jobs, an append-only audit log, and a small versioned public API.

## Status

Planning. The 14-feature plan is in [`feature_list.json`](feature_list.json); the current state is in [`PROGRESS.md`](PROGRESS.md) and the hand-off for the next session is in [`session-handoff.md`](session-handoff.md). No application code yet.

## Quick Start

```bash
# 1. Verify the environment is healthy
./init.sh

# 2. Run full verification (make and npm forms are 1:1 mirrors)
make check          # or: npm run check

# 3. See all available commands
make help           # or: npm run help
```

### Prerequisites

- **Node.js** ≥ 18 — the harness runtime (stack detection, verification layers, session traces)
- **Make** — optional; `npm run <target>` mirrors every make target

## Verification Layers

| Layer | Command | What It Checks |
|-------|---------|----------------|
| 1 | `make lint` / `npm run lint` | Static analysis |
| 1b | `make typecheck` / `npm run typecheck` | Type safety |
| 2 | `make test` / `npm run test` | Unit and integration tests |
| 3 | `make build` / `npm run build` | Build verification |
| 3b | `make e2e` / `npm run e2e` | End-to-end tests |

Layers that are not configured report **SKIP** and do not count as verified.

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
