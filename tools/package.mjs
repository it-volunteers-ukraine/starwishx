/**
 * Copies the deployable theme into production/ - everything except what
 * .distignore lists. The last step of `npm run build`, after
 * `node tools/build.mjs --prod`; CI deploys the folder as it is.
 *
 * Byte-faithful copies (binaries included) that keep their mtimes, so
 * filemtime-based asset versions match the build.
 *
 * File: tools/package.mjs
 */

import fs from "node:fs";
import path from "node:path";
import { ROOT, abs, kb, log, since } from "./build/files.mjs";

const OUT = "production";

/**
 * .distignore -> rules with gitignore semantics: "/" at the start anchors to
 * the theme root (so does one in the middle), "/" at the end matches folders
 * only, "!" re-includes, and a pattern without "/" matches the name at any
 * depth.
 */
function readRules() {
  return fs
    .readFileSync(abs(".distignore"), "utf8")
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line && !line.startsWith("#"))
    .map((line) => {
      const negate = line.startsWith("!");
      let pattern = negate ? line.slice(1) : line;
      const dirOnly = pattern.endsWith("/");
      if (dirOnly) pattern = pattern.slice(0, -1);
      const anchored = pattern.includes("/");
      return { negate, dirOnly, anchored, pattern: pattern.replace(/^\//, "") };
    });
}

function isIgnored(file, isDir, rules) {
  const name = file.slice(file.lastIndexOf("/") + 1);
  let ignored = false;
  for (const rule of rules) {
    if (rule.dirOnly && !isDir) continue;
    if (path.matchesGlob(rule.anchored ? file : name, rule.pattern)) ignored = !rule.negate;
  }
  return ignored;
}

/** Theme-relative paths of every file that ships. */
function collect(rules, dir = "") {
  const files = [];
  for (const entry of fs.readdirSync(abs(dir || "."), { withFileTypes: true })) {
    const file = dir ? `${dir}/${entry.name}` : entry.name;
    const isDir = entry.isDirectory();
    if (isIgnored(file, isDir, rules)) continue;
    if (isDir) files.push(...collect(rules, file));
    else if (entry.isFile()) files.push(file);
  }
  return files;
}

const start = performance.now();
const files = collect(readRules());
fs.rmSync(abs(OUT), { recursive: true, force: true });
let bytes = 0;
for (const file of files) {
  const target = path.join(ROOT, OUT, file);
  const { mtime, atime, size } = fs.statSync(abs(file));
  fs.mkdirSync(path.dirname(target), { recursive: true });
  fs.copyFileSync(abs(file), target);
  fs.utimesSync(target, atime, mtime);
  bytes += size;
}
log("package", `${files.length} files (${kb(bytes)}) -> ${OUT}/ ${since(start)}`);
