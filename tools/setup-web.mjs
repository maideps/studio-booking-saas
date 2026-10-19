#!/usr/bin/env node
// Installs everything the harness gates need for the Laravel app in web/ (npm run setup:web):
// Composer and npm dependencies, a local .env with its own APP_KEY, and the local database.
// Safe to rerun: an existing .env and a key already in it are kept.

import { copyFileSync, existsSync, readFileSync, writeFileSync } from "node:fs";
import path from "node:path";

import { available, run, web } from "./lib/run.mjs";

function step(label, code) {
  if (code !== 0) {
    console.error(`setup:web failed at: ${label}`);
    process.exit(code);
  }
}

for (const tool of ["php", "composer"]) {
  if (!available(tool)) {
    console.error(`${tool} is not on PATH. Install PHP 8.3+ and Composer (for example with Laravel Herd) and rerun.`);
    process.exit(1);
  }
}

step("composer install", run("composer", ["install", "--no-interaction", "--prefer-dist"]));

const env = path.join(web, ".env");
if (!existsSync(env)) copyFileSync(path.join(web, ".env.example"), env);
// Also covers a .env copied by hand, or a run that stopped before the key was written.
if (/^APP_KEY=\s*$/m.test(readFileSync(env, "utf8"))) {
  step("php artisan key:generate", run("php", ["artisan", "key:generate", "--ansi"]));
}

// .env.example uses SQLite for host-side development; Docker Compose uses PostgreSQL.
const sqlite = path.join(web, "database", "database.sqlite");
if (!existsSync(sqlite)) writeFileSync(sqlite, "");
step("php artisan migrate", run("php", ["artisan", "migrate", "--force", "--ansi"]));

step("npm ci", run("npm", ["ci", "--no-audit", "--no-fund"]));
console.log("setup:web complete. Next: npm run check");
