#!/usr/bin/env node
// Runs web/package.json scripts (ESLint, vue-tsc, Vitest, Vite) from the repository root.
//
//   node tools/web.mjs <script>     lint, typecheck, test, build or dev
//
// The harness gates call this launcher, so a missing install or a too-old Node says so
// plainly instead of failing deep inside a tool.

import { existsSync } from "node:fs";
import path from "node:path";

import { run, web } from "./lib/run.mjs";

const MIN_NODE = [20, 19]; // Vite 8

const [script, ...rest] = process.argv.slice(2);
if (!script) {
  console.error("usage: node tools/web.mjs <web/package.json script>");
  process.exit(2);
}
const [major, minor] = process.versions.node.split(".").map(Number);
if (major < MIN_NODE[0] || (major === MIN_NODE[0] && minor < MIN_NODE[1])) {
  console.error(`The front end needs Node ${MIN_NODE.join(".")} or newer (Vite 8); this is Node ${process.versions.node}.`);
  process.exit(1);
}
if (!existsSync(path.join(web, "node_modules"))) {
  console.error("Front-end dependencies are not installed. Run: npm run setup:web");
  process.exit(1);
}
process.exit(run("npm", ["run", "--silent", script, ...(rest.length ? ["--", ...rest] : [])]));
