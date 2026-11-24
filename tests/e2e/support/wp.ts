/**
 * Typed calls to the test-data helper (wp.php), which boots the site's
 * WordPress from the command line. Users are always "e2e-<run>-<name>"; the
 * global setup and teardown delete every "e2e-" user.
 *
 * File: tests/e2e/support/wp.ts
 */
import { execFileSync } from "node:child_process";
import { join } from "node:path";
import { config } from "./config.ts";

const HELPER = join(import.meta.dirname, "wp.php");
const MARKER = "@@E2E@@";

/** One id per run, shared with the workers through the environment. */
const RUN = (process.env.E2E_RUN ??= Date.now().toString(36));

export interface SeededUser {
  login: string;
  email: string;
  password: string;
}

export interface ResetKey {
  key: string;
  /** The link the reset mail would hold (GatewayView::resetUrl). */
  url: string;
}

type Json = Record<string, unknown>;

function run(command: string, ...args: string[]): Json {
  let stdout: string;
  try {
    stdout = execFileSync(
      config.php,
      [...config.phpArgs, HELPER, config.wpPath, command, ...args],
      {
        encoding: "utf8",
        env: { ...process.env, E2E_HOST: new URL(config.baseURL).host },
        stdio: ["ignore", "pipe", "pipe"],
      },
    );
  } catch (error) {
    // A failed command still prints its {error} after the marker.
    const out: unknown = Reflect.get(Object(error), "stdout");
    stdout = typeof out === "string" ? out : "";
    if (!stdout.includes(MARKER)) throw error;
  }
  const at = stdout.lastIndexOf(MARKER);
  if (at < 0) throw new Error(`wp.php ${command}: no result\n${stdout}`);
  const result: unknown = JSON.parse(stdout.slice(at + MARKER.length));
  if (typeof result !== "object" || result === null) {
    throw new Error(`wp.php ${command}: bad result`);
  }
  const json: Json = Object.fromEntries(Object.entries(result));
  if (typeof json.error === "string") {
    throw new Error(`wp.php ${command}: ${json.error}`);
  }
  return json;
}

function str(json: Json, key: string): string {
  const value = json[key];
  if (typeof value !== "string") throw new Error(`wp.php: no "${key}"`);
  return value;
}

export const wp = {
  /** The login a test's user gets: "e2e-<run>-<name>". */
  login: (name: string): string => `e2e-${RUN}-${name}`,

  /** Creates a subscriber, activated unless `inactive`. */
  user(name: string, { inactive = false } = {}): SeededUser {
    const json = run(
      "user",
      wp.login(name),
      ...(inactive ? ["--inactive"] : []),
    );
    return {
      login: str(json, "login"),
      email: str(json, "email"),
      password: str(json, "password"),
    };
  },

  /** A real reset key, as the reset mail would carry it. */
  resetKey(login: string): ResetKey {
    const json = run("reset-key", login);
    return { key: str(json, "key"), url: str(json, "url") };
  },

  /** Deletes every rate-limit counter. */
  clearLimits(): void {
    run("clear-limits");
  },

  /** Deletes every "e2e-" user and the rate-limit counters. */
  cleanup(): void {
    run("cleanup");
  },
};
