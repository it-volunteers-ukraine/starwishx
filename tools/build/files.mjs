/**
 * Shared helpers for the theme build: theme-relative paths, globbing,
 * writing only what changed, content hashes, sizes and console output.
 *
 * File: tools/build/files.mjs
 */

import fs from "node:fs";
import fsp from "node:fs/promises";
import path from "node:path";
import { createHash } from "node:crypto";
import { fileURLToPath } from "node:url";
import { styleText } from "node:util";
import { brotliCompressSync, constants as zlib } from "node:zlib";

/** The theme directory - every path the build reports is relative to it. */
export const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Absolute path -> theme-relative POSIX path ("inc/blocks/faq/view.js"). */
export const rel = (file) => path.relative(ROOT, file).split(path.sep).join("/");

/** Theme-relative path -> absolute path. */
export const abs = (file) => path.join(ROOT, file);

/** Whether a theme-relative path matches any of the glob patterns. */
export const matches = (file, patterns = []) =>
  patterns.some((pattern) => path.matchesGlob(file, pattern));

/**
 * Files (not directories) matching any include pattern and no exclude
 * pattern, theme-relative and sorted. Patterns are node:path globs, so
 * braces and ** work: "inc/*\/Assets/*-store.{js,mjs}".
 */
export function find(include, exclude = []) {
  const found = new Set();
  for (const entry of fs.globSync(include, { cwd: ROOT, withFileTypes: true })) {
    if (!entry.isFile()) continue;
    const file = rel(path.join(entry.parentPath, entry.name));
    if (!matches(file, exclude)) found.add(file);
  }
  return [...found].sort();
}

/**
 * Writes the file only when its bytes change. Unchanged outputs keep their
 * mtime, and BlocksCore versions block assets by filemtime - so a rebuild
 * that changes nothing doesn't bust any browser cache.
 */
export async function writeIfChanged(file, content) {
  const target = abs(file);
  const next = Buffer.isBuffer(content) ? content : Buffer.from(content);
  try {
    if ((await fsp.readFile(target)).equals(next)) return false;
  } catch {
    // Not written yet.
  }
  await fsp.mkdir(path.dirname(target), { recursive: true });
  await fsp.writeFile(target, next);
  return true;
}

/** Byte-faithful copy, skipped when the target is already up to date. */
export async function copyIfChanged(from, to) {
  const source = await fsp.stat(abs(from));
  try {
    const target = await fsp.stat(abs(to));
    // mtimes round-trip through utimes with sub-millisecond loss.
    if (target.size === source.size && Math.abs(target.mtimeMs - source.mtimeMs) < 2) return false;
  } catch {
    // Not copied yet.
  }
  await fsp.mkdir(path.dirname(abs(to)), { recursive: true });
  await fsp.copyFile(abs(from), abs(to));
  // Same mtime as the source, so the next run can tell it is up to date.
  await fsp.utimes(abs(to), source.atime, source.mtime);
  return true;
}

/**
 * A content hash in the shape @wordpress/scripts writes into .asset.php
 * versions: 20 hex characters, changing only when the bytes do.
 */
export const contentHash = (content) =>
  createHash("sha256").update(content).digest("hex").slice(0, 20);

/** Raw and brotli-compressed size of a file, in bytes. */
export function sizesOf(file) {
  const bytes = fs.readFileSync(abs(file));
  const br = brotliCompressSync(bytes, {
    params: { [zlib.BROTLI_PARAM_QUALITY]: zlib.BROTLI_MAX_QUALITY },
  }).length;
  return { raw: bytes.length, br };
}

export const kb = (bytes) => `${(bytes / 1024).toFixed(1)} KB`;

const clock = () => new Date().toTimeString().slice(0, 8);

/** "12:00:01 [lane] message" */
export function log(lane, message) {
  console.log(`${styleText("dim", clock())} ${styleText("cyan", `[${lane}]`)} ${message}`);
}

export function warn(lane, message) {
  console.warn(`${styleText("dim", clock())} ${styleText("yellow", `[${lane}] warning:`)} ${message}`);
}

export function fail(lane, error) {
  const message = error?.message ?? String(error);
  console.error(`${styleText("dim", clock())} ${styleText("red", `[${lane}] error:`)} ${message}`);
}

/** Milliseconds since `start`, for log lines. */
export const since = (start) => `${Math.round(performance.now() - start)} ms`;
