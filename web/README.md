# web/ — the Studio Booking Laravel application

Laravel 13, Vue 3 + TypeScript through Inertia, Pest, Larastan, Pint, ESLint, vue-tsc and Vitest. It lives in this subfolder because the repository root belongs to the harness (D-003 in `../DECISIONS.md`).

Run everything from the repository root:

```bash
npm run setup:web           # composer install, .env with its own APP_KEY, SQLite database, npm ci
npm run check               # every harness layer, including Pint, Larastan, Pest, ESLint, vue-tsc, Vitest and the Vite build
docker compose up --build   # full stack with PostgreSQL, Redis and Mailpit on http://localhost:8000
```

See the root `README.md` and `docs/ARCHITECTURE.md` for the layout and the rules.
