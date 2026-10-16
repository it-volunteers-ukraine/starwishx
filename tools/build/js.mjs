/**
 * JS lanes: Rolldown builds, one per entry file.
 *
 * Oxc (inside Rolldown) does what Babel did: JSX for the editor lane and a
 * syntax floor. Script modules only run in browsers with import maps, which
 * already speak ES2022, so nothing below that is ever lowered or polyfilled.
 *
 * File: tools/build/js.mjs
 */

import { build, watch } from "rolldown";
import { abs, fail, find, log, rel, since, warn } from "./files.mjs";
import { scriptGlobal, wordpress } from "./wp.mjs";

const TARGET = "es2022";

/** Rolldown options for one entry of a lane (see tools/build/lanes.mjs). */
function options(lane, entry, { prod }, onWrite) {
  return {
    input: abs(entry),
    platform: "browser",
    // The editor lane writes JSX in plain .js files.
    moduleTypes: lane.jsx ? { ".js": "jsx" } : undefined,
    transform: {
      target: TARGET,
      // development stays false even in dev builds - the dev JSX runtime would
      // put "react/jsx-dev-runtime" into editor.asset.php, a dependency
      // WordPress has no script handle for, and the editor script would
      // silently never load.
      jsx: lane.jsx ? { runtime: "automatic", development: false } : undefined,
      define: {
        "process.env.NODE_ENV": JSON.stringify(
          prod ? "production" : "development",
        ),
      },
    },
    plugins: [wordpress(lane, onWrite)],
    onLog(level, entryLog) {
      if (level === "warn") warn(lane.id, `${entry}: ${entryLog.message}`);
    },
    output: {
      file: abs(lane.out(entry)),
      format: lane.format,
      minify: prod,
      // Separate .map files, never eval(): nothing needs a CSP exception.
      sourcemap: !prod,
      // Minified output keeps no comments at all - no extracted LICENSE files.
      comments: prod ? false : undefined,
      globals: lane.wp === "scripts" ? scriptGlobal : undefined,
    },
  };
}

/** Builds every entry of the JS lanes once. Resolves to the failure count. */
export async function buildJs(lanes, context) {
  let failures = 0;
  await Promise.all(
    lanes.map(async (lane) => {
      const start = performance.now();
      const entries = find(lane.include, lane.exclude);
      await Promise.all(
        entries.map((entry) =>
          build(options(lane, entry, context)).catch((error) => {
            failures++;
            fail(lane.id, `${entry}\n${error.message}`);
          }),
        ),
      );
      log(lane.id, `${entries.length} bundles ${since(start)}`);
    }),
  );
  return failures;
}

/** The entry files of the JS lanes, to notice when one is added or removed. */
export const jsEntries = (lanes) =>
  lanes.flatMap((lane) => find(lane.include, lane.exclude)).join("\n");

/**
 * Builds and then watches every JS entry; Rolldown follows each import graph
 * itself and rebuilds only the bundles a change touches. The first round is
 * summed up per lane, later rebuilds are logged one bundle at a time.
 * Returns the watcher.
 */
export function watchJs(lanes, context) {
  const start = performance.now();
  const firstRound = new Map();
  let watching = false;

  const all = lanes.flatMap((lane) =>
    find(lane.include, lane.exclude).map((entry) => {
      const onWrite = (file, ms) => {
        if (watching) log(lane.id, `${rel(file)} ${ms} ms`);
        else firstRound.set(lane.id, (firstRound.get(lane.id) ?? 0) + 1);
      };
      return {
        ...options(lane, entry, context, onWrite),
        // One rebuild per save, even when an editor writes the file twice.
        watch: { buildDelay: 50 },
      };
    }),
  );
  const watcher = watch(all);

  watcher.on("event", (event) => {
    if (event.code === "BUNDLE_END") {
      event.result.close();
    } else if (event.code === "ERROR") {
      // A deleted entry fails once before the watcher is restarted without it.
      if (!/Cannot resolve entry module/.test(event.error?.message))
        fail("js", event.error);
      event.result?.close();
    } else if (event.code === "END" && !watching) {
      watching = true;
      for (const [lane, count] of firstRound) log(lane, `${count} bundles`);
      log("js", `${all.length} bundles watched ${since(start)}`);
    }
  });
  return watcher;
}
