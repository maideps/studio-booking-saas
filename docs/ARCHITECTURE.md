# Architecture

## Layer Model

This project follows a layered structure:

```
┌─────────────────────────────────────────────┐
│               Presentation Layer             │
│  (UI, CLI, API endpoints — user-facing)     │
├─────────────────────────────────────────────┤
│               Application Layer              │
│  (Use cases, orchestration, workflows)       │
├─────────────────────────────────────────────┤
│                 Domain Layer                  │
│  (Business logic, entities, rules)           │
├─────────────────────────────────────────────┤
│             Infrastructure Layer             │
│  (Persistence, networking, external APIs)    │
└─────────────────────────────────────────────┘
```

## Harness Boundaries

The harness contract (described in `.harness/manifest.json` and enforced by `make check-arch` / `npm run check-arch`) separates:

- **CORE** — files that ship as-is and must not be edited (runner, scripts, templates, root operating manual).
- **TEMPLATES** — adopter skeletons under `templates/`.
- **INSTANCE** — this project's own state (feature list, progress, decisions, docs).
- **PRODUCT-OWNED** — directories listed in the manifest's `productRoots`.

Verification entrypoints delegate to the Node runner (`scripts/framework-check.mjs`); Makefile targets and npm scripts are 1:1 mirrors.

## Worktree Lanes

Multiple agents can work concurrently, each in its own sibling git worktree on branch `lane/<feature-id>` (`make lane-new F=<id>`, `lane-list`, `lane-land F=<id>`, `lane-drop F=<id>`). A branch can only be checked out in one worktree at a time, so git's own branch-checkout exclusivity is the coordination primitive — no lock file needed. WIP=1 is enforced per lane: a `lane/<feature-id>` branch may only activate the feature it is named for.

## Sharded Feature State

`feature_list.json` declares what each feature is and rarely changes; each feature's mutable state (`state`, `evidence`) lives in its own file, `.harness/state/<feature-id>.json`. `verify-feature` writes only that shard, never the shared declarations file — two lanes finishing two different features merge without conflict. `npm run migrate-feature-state` moves an older repo's inline state into shards.

## Application Dependency Rules

- Layers may only depend on layers below them
- Domain layer has zero external dependencies
- Infrastructure implements interfaces defined by domain
- Presentation never directly accesses infrastructure

Encode these rules as entries in `.harness/arch-rules.json` as the codebase grows.

## Enforcement

```bash
make check-arch     # or: npm run check-arch
```

## Module Documentation

Co-locate architecture notes with the modules they affect; record framework-wide decisions in the root `DECISIONS.md`.


## Product Architecture

```
Browser (Vue 3 + TypeScript via Inertia)  --->  Laravel app  --->  PostgreSQL
Booking widget (public JSON API)          --->       |
                                                     +--->  Redis (queues, cache, locks)  --->  Queue worker + scheduler
                                                     +--->  Stripe (test mode) <--- webhooks
                                                     +--->  Mailpit (local email capture)
```

Product directories (declared in `.harness/manifest.json` as `productRoots`):

- `web/` — the Laravel application (PHP and Vue sources, `composer.json`, its own `package.json`). It lives in a subfolder because the repository root `package.json` belongs to the harness.
- `tools/` — small launchers that run PHP and Node tooling from the harness gates on any operating system.
- `docker/`, `docker-compose.yml` — local stack (app, queue worker, scheduler, PostgreSQL, Redis, Mailpit).
- `docs/sprints/` — one sprint contract per feature.

Layering inside `web/app/` (dependencies point downward only):

- **Presentation** — HTTP controllers, Inertia pages, API resources, form requests.
- **Application** — actions (one use case per class), jobs, listeners.
- **Domain** — models and their rules, enums, domain events, policies. No dependency on Stripe, HTTP or the queue.
- **Infrastructure** — Stripe gateway, mail, storage; implements interfaces the domain defines.

Key design rules:

- **Tenancy:** one database, a `tenant_id` column on every tenant-owned table (including users), a global scope applied by a `BelongsToTenant` trait, tenant resolved from the request (subdomain) and carried explicitly into queued jobs (D-002).
- **Capacity:** booking inside a transaction with a row lock on the session; a concurrency test fires parallel requests and asserts no overbooking.
- **Billing:** webhook handlers verify the Stripe signature, record each event id, and are safe to replay.
- **Audit:** state changes append rows to an audit table that is never updated or deleted.
