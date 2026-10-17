# Project Standards

Single source of truth for this project's coding standards. Keep this file current — it is what agents and reviewers check code against.

## Core Principles

- Small, focused changes, one feature at a time (harness WIP=1), each with a sprint contract in `docs/sprints/`.
- Every new behavior ships with tests; behavior that touches money, capacity or tenant data ships with tests for the failure and concurrency cases too.
- Full type safety: PHP strict types, Larastan at a high level, TypeScript `strict`.
- Human review is not optional: agent output is a draft, and decisions that change architecture are recorded in `DECISIONS.md`.
- No stale documentation: update docs in the same commit as the code.

## Language and Tooling

- PHP 8.3+ with `declare(strict_types=1)` in every PHP file; Laravel (current stable); Composer.
- Vue 3 with `<script setup lang="ts">`, TypeScript `strict`, Inertia; Node >= 18 (the harness runtime) and the version in `.nvmrc`.
- Formatting and static analysis: Laravel Pint (PHP formatting), Larastan (PHP types), ESLint and vue-tsc (front end). Configuration lives in `web/`.
- Tests: Pest for PHP, Vitest for components, Playwright for end-to-end. All run through the harness gates (`npm run lint`, `typecheck`, `test`, `build`).

## Testing Discipline

- New behavior starts with a failing test.
- **Tenancy rule:** every model that holds tenant data uses the `BelongsToTenant` trait. Any query that bypasses the tenant scope goes through one named helper, and an architecture test fails if the bypass appears anywhere else.
- Every role-protected action has a test per role (owner, staff, customer, and a user from another tenant).
- Money and capacity paths are tested for retries, duplicates and parallel requests.
- Tests use fakes for Stripe and mail; they never call the network.
- A passing suite means `npm run check` passes before every commit.

## Naming and Structure

- Actions are single-purpose classes in `app/Actions/<Area>/` named by verb (`BookSession`, `CancelBooking`).
- Enums for states and roles; no magic strings.
- Branches and features: `feat-NNN` ids from `feature_list.json`; lane branches use `lane/<feature-id>`.
- Database: one migration per change, never edited after merge; every tenant-owned table has `tenant_id` with an index.

## Commit and Review Standards

- Conventional style: `feat(feat-005): ...`, `fix: ...`, `docs: ...`; the message explains why.
- One logical change per commit; the repository passes `npm run check` after every commit.
- Run an independent review pass (`skills/review`) before the feature gate.

## Documentation Standards

- Update `README.md`, `docs/ARCHITECTURE.md` and `DECISIONS.md` in the same commit as the change that affects them.
- Session notes go in `progress/NNN-YYYY-MM-DD.md`; regenerate `PROGRESS.md` with `node scripts/framework-check.mjs progress-update`.
- Do not invent metrics: numbers in docs come from commands that can be re-run.
