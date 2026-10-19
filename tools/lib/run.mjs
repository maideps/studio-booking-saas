// Shared helpers for the tools/ launchers: where the Laravel app lives and how to start a
// command there on Windows and Linux alike.

import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

export const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..", "..");
export const web = path.join(root, "web");

const windows = process.platform === "win32";

// On Windows, php, composer and npm are .bat/.cmd shims (Herd, Composer-Setup, Node), which
// only start through cmd.exe. Node will not quote arguments for a shell, so build the command
// line here: arguments with spaces or cmd metacharacters are wrapped in double quotes (inside
// which cmd treats & | < > ^ literally). Quotes and % cannot be passed safely, so refuse them.
function windowsCommandLine(command, args) {
  const quoted = args.map((arg) => {
    if (/["%]/.test(arg)) {
      throw new Error(`cannot pass ${JSON.stringify(arg)} through cmd.exe; remove the quote or percent sign`);
    }
    return arg === "" || /[\s&|<>^()]/.test(arg) ? `"${arg}"` : arg;
  });
  return [command, ...quoted].join(" ");
}

function spawn(command, args, options) {
  const settings = { cwd: web, ...options };
  return windows
    ? spawnSync(windowsCommandLine(command, args), { ...settings, shell: true })
    : spawnSync(command, args, settings);
}

/** Run `command args…` inside web/ with inherited output; returns the exit code. */
export function run(command, args, options = {}) {
  let result;
  try {
    result = spawn(command, args, { stdio: "inherit", ...options });
  } catch (error) {
    console.error(`Could not start ${command}: ${error.message}`);
    return 2;
  }
  if (result.error) {
    console.error(`Could not start ${command}: ${result.error.message}`);
    return 1;
  }
  return result.status ?? 1;
}

/** True when `command --version` starts and exits 0. */
export function available(command) {
  return spawn(command, ["--version"], { stdio: "ignore" }).status === 0;
}
