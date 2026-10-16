/**
 * Theme build: JS (Rolldown), CSS (sass-embedded -> Lightning CSS), the icon
 * sprite and static files. The outputs themselves are declared in
 * tools/build/lanes.mjs.
 *
 *   node tools/build.mjs            dev build: source maps, no minification
 *   node tools/build.mjs --watch    dev build, then rebuild whatever changes
 *   node tools/build.mjs --prod     clean production build + size report
 *   node tools/build.mjs --clean    remove all build output (and production/)
 *   --only=styles,stores            limit the run to some lanes, by id
 *
 * `npm run build` also copies the deployable theme into production/
 * (tools/package.mjs). Translations are not built here - they need wp-cli;
 * see the i18n:* scripts in package.json.
 *
 * File: tools/build.mjs
 */

import fs from "node:fs";
import path from "node:path";
import { parseArgs } from "node:util";
import { buildSprite } from "./build-sprite.mjs";
import { buildCss, closeSass, rebuildCss } from "./build/css.mjs";
import { abs, copyIfChanged, fail, find, kb, log, matches, rel, since, sizesOf } from "./build/files.mjs";
import { buildJs, jsEntries, watchJs } from "./build/js.mjs";
import { lanes, outputDirs } from "./build/lanes.mjs";

const { values: args } = parseArgs({
  options: {
    prod: { type: "boolean", default: false },
    watch: { type: "boolean", default: false },
    clean: { type: "boolean", default: false },
    only: { type: "string" },
  },
});

const context = { prod: args.prod };

function selectLanes() {
  if (!args.only) return lanes;
  const ids = args.only.split(",").map((id) => id.trim());
  const unknown = ids.filter((id) => !lanes.some((lane) => lane.id === id));
  if (unknown.length) {
    throw new Error(`Unknown lane ${unknown.join(", ")} - lanes: ${lanes.map((lane) => lane.id).join(", ")}`);
  }
  return lanes.filter((lane) => ids.includes(lane.id));
}

function clean({ production }) {
  const dirs = outputDirs.flatMap((pattern) =>
    pattern.includes("*") ? fs.globSync(pattern, { cwd: abs(".") }) : [pattern],
  );
  if (production) dirs.push("production");
  for (const dir of dirs) fs.rmSync(abs(dir), { recursive: true, force: true });
  log("clean", dirs.map((dir) => rel(abs(dir))).join(", "));
}

async function runSprite() {
  const start = performance.now();
  const { count } = await buildSprite({ quiet: true });
  log("sprite", `${count} icons -> src/img/sprites.svg ${since(start)}`);
}

/** Copies one changed file if a copy set covers it; true when it did. */
async function copyOne(lane, file) {
  for (const set of lane.sets) {
    if (!matches(file, set.include) || matches(file, set.exclude)) continue;
    if (!fs.existsSync(abs(file))) return false;
    if (await copyIfChanged(file, set.to(file))) log(lane.id, set.to(file));
    return true;
  }
  return false;
}

async function copyAll(lane) {
  const start = performance.now();
  let copied = 0;
  let total = 0;
  for (const set of lane.sets) {
    for (const file of find(set.include, set.exclude)) {
      total++;
      if (await copyIfChanged(file, set.to(file))) copied++;
    }
  }
  log(lane.id, `${copied} of ${total} files copied ${since(start)}`);
}

/** Raw and brotli sizes of every JS and CSS output, grouped by lane. */
function report(selected) {
  const rows = [];
  for (const lane of selected) {
    if (lane.type !== "js" && lane.type !== "css") continue;
    for (const entry of find(lane.include, lane.exclude)) {
      const out = lane.out(entry);
      if (fs.existsSync(abs(out))) rows.push({ lane: lane.id, out, ...sizesOf(out) });
    }
  }
  if (!rows.length) return;
  const width = Math.max(...rows.map((row) => row.out.length));
  console.log(`\n${"output".padEnd(width)}  ${"raw".padStart(9)}  ${"brotli".padStart(9)}`);
  let lane;
  for (const row of rows) {
    if (row.lane !== lane) console.log(`-- ${(lane = row.lane)}`);
    console.log(`${row.out.padEnd(width)}  ${kb(row.raw).padStart(9)}  ${kb(row.br).padStart(9)}`);
  }
  const sum = (key) => rows.reduce((total, row) => total + row[key], 0);
  console.log(`${"total".padEnd(width)}  ${kb(sum("raw")).padStart(9)}  ${kb(sum("br")).padStart(9)}\n`);
}

/** One pass over every lane; in watch mode Rolldown's watcher does the JS. */
async function buildOnce(selected) {
  const sprite = selected.find((lane) => lane.type === "sprite");
  const files = selected.find((lane) => lane.type === "copy");
  // The sprite comes first: the files lane ships the sprites.svg it writes.
  if (sprite) await runSprite();
  const [jsFailures, cssFailures] = await Promise.all([
    args.watch ? 0 : buildJs(selected.filter((lane) => lane.type === "js"), context),
    buildCss(selected.filter((lane) => lane.type === "css"), context),
    files ? copyAll(files) : null,
  ]);
  return jsFailures + cssFailures;
}

/**
 * JS edits are Rolldown's (it watches each import graph); this watcher
 * handles SCSS, the sprite, static files, and JS entries appearing or
 * disappearing, which need a new Rolldown watcher.
 */
function watchAll(selected) {
  const jsLanes = selected.filter((lane) => lane.type === "js");
  const cssLanes = selected.filter((lane) => lane.type === "css");
  const sprite = selected.find((lane) => lane.type === "sprite");
  const files = selected.find((lane) => lane.type === "copy");

  let jsWatcher = jsLanes.length ? watchJs(jsLanes, context) : null;
  let entries = jsEntries(jsLanes);
  const changed = new Set();
  let timer;
  let running = Promise.resolve();

  async function flush(batch) {
    for (const file of batch) {
      if (file.endsWith(".scss") && cssLanes.length) await rebuildCss(cssLanes, file, context);
      if (sprite && matches(file, sprite.include)) {
        await runSprite().catch((error) => fail("sprite", error));
        if (files) await copyOne(files, "src/img/sprites.svg");
      }
      if (files) await copyOne(files, file);
    }
    if (jsLanes.length && batch.some((file) => /\.m?js$/.test(file))) {
      const next = jsEntries(jsLanes);
      if (next !== entries) {
        entries = next;
        await jsWatcher?.close();
        jsWatcher = watchJs(jsLanes, context);
        log("js", "entry files changed - watcher restarted");
      }
    }
  }

  const onEvent = (dir) => (_event, name) => {
    if (!name) return;
    const file = rel(path.join(abs(dir), name));
    // Our own output (inc/blocks/*/build/) is not a source.
    if (file.split("/").includes("build")) return;
    changed.add(file);
    clearTimeout(timer);
    timer = setTimeout(() => {
      const batch = [...changed];
      changed.clear();
      running = running.then(() => flush(batch)).catch((error) => fail("watch", error));
    }, 60);
  };
  const watchers = ["src", "inc"].map((dir) => fs.watch(abs(dir), { recursive: true }, onEvent(dir)));
  log("watch", "watching src/ and inc/ - Ctrl+C to stop");

  process.once("SIGINT", async () => {
    for (const watcher of watchers) watcher.close();
    await jsWatcher?.close();
    await closeSass();
    process.exit(0);
  });
}

try {
  const selected = selectLanes();
  if (args.clean) {
    clean({ production: true });
  } else {
    const start = performance.now();
    // A full production build starts clean, so no dev source maps or outputs
    // of deleted entries end up in production/. With --only the other lanes'
    // outputs must survive, so nothing is cleaned.
    if (args.prod && !args.only) clean({ production: false });
    const failures = await buildOnce(selected);
    log(args.prod ? "prod" : "dev", `${failures ? `${failures} failed` : "built"} in ${since(start)}`);
    if (args.prod) report(selected);
    if (args.watch) watchAll(selected);
    else process.exitCode = failures ? 1 : 0;
  }
} catch (error) {
  fail("build", error);
  process.exitCode = 1;
} finally {
  if (!args.watch) await closeSass();
}
