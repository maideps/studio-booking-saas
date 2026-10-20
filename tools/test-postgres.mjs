#!/usr/bin/env node
// Runs the Pest tests that need PostgreSQL (the `postgres` group, web/tests/Concurrency/)
// against a throwaway PostgreSQL container, then removes it (D-005). Part of npm run test.
//
// The SQLite suite cannot show row locks or parallel requests, so these tests are part of
// the proof for bookings: without Docker this step fails instead of skipping.

import { spawnSync } from "node:child_process";
import { existsSync } from "node:fs";
import path from "node:path";

import { available, root, run, web } from "./lib/run.mjs";

const COMPOSE_FILE = path.join(root, "docker", "test-postgres.compose.yml");
const PORT = process.env.TEST_DB_PORT ?? "55432";

const log = (message) => console.log(`[test-postgres] ${message}`);

function compose(args, options = {}) {
  return spawnSync("docker", ["compose", "-f", COMPOSE_FILE, ...args], {
    cwd: root,
    env: { ...process.env, TEST_DB_PORT: PORT },
    stdio: "inherit",
    ...options,
  });
}

function fail(message) {
  console.error(`[test-postgres] ${message}`);
  process.exit(1);
}

if (spawnSync("docker", ["info"], { stdio: "ignore" }).status !== 0) {
  fail("Docker is not running. The concurrency tests need PostgreSQL (D-005): start Docker Desktop and rerun.");
}
if (!available("php") || !existsSync(path.join(web, "vendor", "autoload.php"))) {
  fail("PHP or the PHP dependencies are missing. Run: npm run setup:web");
}

compose(["down", "--volumes", "--remove-orphans"], { stdio: "ignore" });
log(`starting PostgreSQL on 127.0.0.1:${PORT}`);
if (compose(["up", "--detach", "--wait", "--wait-timeout", "120"]).status !== 0) {
  compose(["down", "--volumes"], { stdio: "ignore" });
  fail("docker compose up failed");
}

let status;
try {
  // These variables override the SQLite settings in phpunit.xml, which only fills in
  // variables that are not set yet.
  status = run("php", ["artisan", "test", "--group=postgres", "--compact"], {
    env: {
      ...process.env,
      DB_CONNECTION: "pgsql",
      DB_HOST: "127.0.0.1",
      DB_PORT: PORT,
      DB_DATABASE: "studio_booking_test",
      DB_USERNAME: "studio_booking",
      DB_PASSWORD: "studio_booking",
      DB_URL: "",
    },
  });
} finally {
  log("removing PostgreSQL");
  compose(["down", "--volumes"], { stdio: "ignore" });
}
process.exit(status);
