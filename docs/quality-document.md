# Quality Document

Rate each module on a scale of A (excellent) / B (good) / C (acceptable) / D (needs work) / F (failing).

## Modules

| Module | Test Coverage | Type Safety | Documentation | Arch Compliance | Performance |
|---|---|---|---|---|---|
| harness scaffold | B | n/a | B | B | n/a |
| web (Laravel + Vue) skeleton | B | A | B | B | n/a |
| tools/ launchers and Docker stack | C | n/a | B | B | n/a |
| web tenancy (`app/Tenancy`, `Tenant`, subdomain middleware) | A | A | A | A | B |

## Summary

- **Overall Quality Grade:** B (tenancy foundation in place; no booking behavior yet)
- **Blockers:** none
- **Areas requiring attention:**
  - PHP tests run on SQLite. Move them to PostgreSQL before feat-005, whose locking tests need it. The tenancy tests use only portable SQL. The new migrations ran on PostgreSQL only through `npm run smoke` (the app migrates on start), not under test.
  - The scope-bypass rule is a token scan of our own code; Laravel internals that skip global scopes (validation `unique`/`exists`, queue model restoration) are not covered by it (see `docs/ARCHITECTURE.md`, Tenancy).
  - Tenancy performance is graded B: each scoped query adds one indexed `tenant_id` condition, and each tenant-aware job does one extra lookup of its tenant. Not measured.
  - The launchers and the Compose stack are tested only by running them (`npm run check`, `npm run smoke`); they have no unit tests of their own.

## Notes

- Session 001 created the harness scaffold, the project docs, the standards and the 14-feature plan.
- Session 002 (feat-001) added the Laravel 13 app in `web/` with its tests:
  - Pest: the health route, the Inertia welcome page, and architecture rules for strict types and leftover debug calls.
  - Vitest: the welcome page.
  - Type checks: Larastan level 8 and vue-tsc in strict mode.
  - Formatting: Pint, with `declare(strict_types=1)` enforced.
- Session 003 (feat-002) added the tenancy foundation. Pest now has 47 tests:
  - isolation of tenant A from tenant B in queries, relations, bulk updates, routes (with route model binding) and queued jobs, including one run through a real `database` queue worker;
  - write guards and fail-closed behaviour;
  - architecture rules for the trait, `tenant_id` indexes and foreign keys, tenant-aware jobs, scope bypasses and middleware order, each rule also fed a violation.
  - Each mechanism was also broken on purpose once (trait removed, bypass added, middleware priority removed, job middleware removed), and the matching tests failed.

---

_Update this document at session close for modules touched in the session._
