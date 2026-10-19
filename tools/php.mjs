#!/usr/bin/env node
// Runs PHP inside web/ from the repository root, the way tools/web.mjs runs the front end.
//
//   node tools/php.mjs vendor/bin/pint --test     formatting check (npm run lint)
//   node tools/php.mjs vendor/bin/phpstan analyse  Larastan (npm run typecheck)
//   node tools/php.mjs artisan test                Pest (npm run test)
//
// The harness gates call this launcher, so a missing PHP or a missing `composer install`
// says so plainly instead of failing deep inside a tool.

import { existsSync } from "node:fs";
import path from "node:path";

import { available, run, web } from "./lib/run.mjs";

const args = process.argv.slice(2);
if (args.length === 0) {
  console.error("usage: node tools/php.mjs <php arguments>");
  process.exit(2);
}
if (!available("php")) {
  console.error("PHP 8.3+ is not on PATH. Install it (for example with Laravel Herd) and rerun.");
  process.exit(1);
}
if (!existsSync(path.join(web, "vendor", "autoload.php"))) {
  console.error("PHP dependencies are not installed. Run: npm run setup:web");
  process.exit(1);
}
process.exit(run("php", args));
