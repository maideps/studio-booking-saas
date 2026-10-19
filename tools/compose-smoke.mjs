#!/usr/bin/env node
// Smoke test for the Docker Compose stack (npm run smoke): builds the image, starts every
// service as a separate, throwaway compose project on its own ports, checks that the app
// serves /up, the Inertia welcome page and its built script, and that no container stopped or
// restarted (the worker and scheduler have no health check of their own), then
// removes the containers and volumes. A stack you already have running is never touched.
//
// Not part of npm run check: building the image takes minutes. Run it after changing
// docker/, docker-compose.yml or the app's runtime configuration.

import { spawnSync } from "node:child_process";

import { root } from "./lib/run.mjs";

const PROJECT = "studio-booking-smoke";
const PORTS = { APP_PORT: 18000, MAILPIT_PORT: 18025, DB_FORWARD_PORT: 15432, REDIS_FORWARD_PORT: 16379 };
const base = `http://localhost:${PORTS.APP_PORT}`;
const env = { ...process.env, ...Object.fromEntries(Object.entries(PORTS).map(([k, v]) => [k, String(v)])) };

const log = (message) => console.log(`[smoke] ${message}`);

function compose(args, options = {}) {
  return spawnSync("docker", ["compose", "-p", PROJECT, ...args], { cwd: root, env, stdio: "inherit", ...options });
}

function get(pathname) {
  return fetch(`${base}${pathname}`, { signal: AbortSignal.timeout(15_000) });
}

function check(condition, message) {
  if (!condition) throw new Error(message);
}

async function main() {
  check(spawnSync("docker", ["info"], { stdio: "ignore" }).status === 0, "Docker is not running. Start Docker Desktop and rerun.");
  compose(["down", "--volumes", "--remove-orphans"], { stdio: "ignore" });

  log("building and starting the stack (waits for the health checks)");
  check(compose(["up", "--detach", "--build", "--wait", "--wait-timeout", "300"]).status === 0, "docker compose up failed");

  log(`GET ${base}/up`);
  const health = await get("/up");
  check(health.ok, `/up answered ${health.status}`);

  log(`GET ${base}/`);
  const home = await get("/");
  const html = await home.text();
  check(home.ok, `/ answered ${home.status}`);
  check(/component(&quot;|")\s*:\s*(&quot;|")Welcome(&quot;|")/.test(html), "/ did not render the Welcome Inertia page");
  const script = html.match(/src="(?:https?:\/\/[^/"]+)?(\/build\/assets\/app-[^"]+\.js)"/);
  check(script, "/ does not load the built front-end script");
  log(`GET ${base}${script[1]}`);
  const asset = await get(script[1]);
  check(asset.ok, `${script[1]} answered ${asset.status}`);

  // The worker and scheduler have no port or health check. Give them time to fail, then
  // require every container to be running and never restarted: restart: unless-stopped would
  // otherwise hide a crash loop behind a "running" state.
  await new Promise((resolve) => setTimeout(resolve, 10_000));
  const ids = compose(["ps", "--all", "--quiet"], { stdio: ["ignore", "pipe", "inherit"], encoding: "utf8" });
  const inspect = spawnSync(
    "docker",
    ["inspect", "--format", '{{index .Config.Labels "com.docker.compose.service"}} {{.State.Status}} {{.RestartCount}}', ...ids.stdout.trim().split(/\s+/)],
    { encoding: "utf8" },
  );
  check(inspect.status === 0, "docker inspect failed");
  const containers = Object.fromEntries(
    inspect.stdout.trim().split(/\r?\n/).map((line) => {
      const [service, status, restarts] = line.split(" ");
      return [service, { status, restarts: Number(restarts) }];
    }),
  );
  for (const service of ["app", "worker", "scheduler", "postgres", "redis", "mailpit"]) {
    const container = containers[service];
    check(container, `${service} is missing`);
    check(container.status === "running", `${service} is ${container.status}, expected running`);
    check(container.restarts === 0, `${service} restarted ${container.restarts} time(s)`);
  }
  log(`running with no restarts: ${Object.keys(containers).sort().join(", ")}`);
  log("PASS");
}

let code = 1;
try {
  await main();
  code = 0;
} catch (error) {
  console.error(`[smoke] FAIL: ${error.message}`);
  compose(["logs", "--tail", "40"]);
} finally {
  log("removing the stack");
  compose(["down", "--volumes", "--remove-orphans"], { stdio: "ignore" });
}
process.exit(code);
