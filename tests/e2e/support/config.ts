/**
 * Where the E2E suite runs: env vars, else the gitignored
 * tests/e2e/.env.local.json, e.g.
 *
 *   {
 *     "baseURL": "https://starwish.local",
 *     "wpPath": "D:/…/app/public",
 *     "php": "C:/Users/…/herd/bin/php85/php.exe",
 *     "phpArgs": ["-d", "mysqli.default_port=10114"]
 *   }
 *
 * File: tests/e2e/support/config.ts
 */
import { existsSync, readFileSync } from "node:fs";
import { join } from "node:path";

export interface E2EConfig {
  /** The site, without a trailing slash. */
  baseURL: string;
  /** The WordPress folder (wp-load.php) for the test-data helper. */
  wpPath: string;
  /** The PHP that runs the helper, and its arguments. */
  php: string;
  phpArgs: string[];
  /** Playwright's browser channel: "msedge" locally, "chromium" in CI. */
  channel: string;
}

type LocalFile = Partial<Omit<E2EConfig, "channel">> & { channel?: string };

const isStringArray = (value: unknown): value is string[] =>
  Array.isArray(value) && value.every((v) => typeof v === "string");

function readLocalFile(): LocalFile {
  const file = join(import.meta.dirname, "..", ".env.local.json");
  if (!existsSync(file)) return {};
  const raw: unknown = JSON.parse(
    readFileSync(file, "utf8").replace(/^\uFEFF/, ""),
  );
  if (typeof raw !== "object" || raw === null) return {};
  const local: LocalFile = {};
  for (const key of ["baseURL", "wpPath", "php", "channel"] as const) {
    const value: unknown = Reflect.get(raw, key);
    if (typeof value === "string") local[key] = value;
  }
  const phpArgs: unknown = Reflect.get(raw, "phpArgs");
  if (isStringArray(phpArgs)) local.phpArgs = phpArgs;
  return local;
}

function load(): E2EConfig {
  const local = readLocalFile();
  const env = process.env;
  const baseURL = env.E2E_BASE_URL ?? local.baseURL;
  const wpPath = env.E2E_WP_PATH ?? local.wpPath;
  if (!baseURL || !wpPath) {
    throw new Error(
      "E2E: set E2E_BASE_URL and E2E_WP_PATH, or write tests/e2e/.env.local.json (see tests/README.md).",
    );
  }
  return {
    baseURL: baseURL.replace(/\/+$/, ""),
    wpPath,
    php: env.E2E_PHP ?? local.php ?? "php",
    phpArgs:
      env.E2E_PHP_ARGS?.split(" ").filter(Boolean) ?? local.phpArgs ?? [],
    channel: env.E2E_CHANNEL ?? local.channel ?? "msedge",
  };
}

export const config: E2EConfig = load();
