/**
 * CSS lanes: sass-embedded compiles the SCSS, then a single Lightning CSS
 * pass adds the vendor prefixes the package.json "browserslist" targets
 * need, lowers syntax they lack, minifies (prod) and scopes CSS modules
 * (legacy ACF lane) - what autoprefixer, cssnano and postcss-modules did.
 *
 * File: tools/build/css.mjs
 */

import path from "node:path";
import { fileURLToPath } from "node:url";
import browserslist from "browserslist";
import { browserslistToTargets, transform } from "lightningcss";
import { initAsyncCompiler } from "sass-embedded";
import {
  ROOT,
  abs,
  contentHash,
  fail,
  find,
  log,
  rel,
  since,
  warn,
  writeIfChanged,
} from "./files.mjs";
import { assetPhp } from "./wp.mjs";

const targets = browserslistToTargets(browserslist(undefined, { path: ROOT }));

/** Every stylesheet an entry pulled in, itself included - drives watch. */
const graph = new Map();

/** cssModules JSON file -> Map(module key -> { local: scoped name }). */
const moduleMaps = new Map();

let compiler;
/** One long-lived Dart Sass process for the whole run. */
const sass = () => (compiler ??= initAsyncCompiler());

export async function closeSass() {
  if (compiler) await (await compiler).dispose();
}

/**
 * A Sass error as "src/scss/x.scss:12:5: message" plus the offending line -
 * Sass' own message prints paths relative to its compiler's directory.
 */
function sassError(error) {
  if (!error?.sassMessage || !error.span?.url) return error;
  const { span } = error;
  const where = `${rel(fileURLToPath(span.url))}:${span.start.line + 1}:${span.start.column + 1}`;
  return new Error(
    `${where}: ${error.sassMessage}\n  ${(span.context ?? span.text).trimEnd()}`,
  );
}

/** Sass source-map URLs -> paths relative to the .map file, for DevTools. */
function relativeSources(map, out) {
  const dir = path.dirname(abs(out));
  map.sources = map.sources.map((source) =>
    source.startsWith("file:")
      ? path.relative(dir, fileURLToPath(source)).split(path.sep).join("/")
      : source,
  );
  map.file = path.basename(out);
  return map;
}

async function compile(lane, entry, { prod }) {
  const out = lane.out(entry);
  const result = await (
    await sass()
  ).compileAsync(abs(entry), {
    loadPaths: (lane.loadPaths ?? []).map(abs),
    sourceMap: !prod,
    sourceMapIncludeSources: !prod,
  });
  graph.set(
    entry,
    new Set(
      result.loadedUrls
        .filter((url) => url.protocol === "file:")
        .map((url) => rel(fileURLToPath(url))),
    ),
  );

  const css = transform({
    // Lightning CSS names module classes after the file: passing
    // "contact-module.css" for contact.module.scss gives
    // contact-module__title___<hash>, the scheme postcss-modules used. The
    // path is theme-relative so the hash is the same on every machine.
    filename: lane.cssModules
      ? entry.replace(/\.module\.scss$/, "-module.css")
      : out,
    code: Buffer.from(result.css),
    targets,
    minify: prod,
    sourceMap: !prod,
    inputSourceMap: result.sourceMap
      ? JSON.stringify(result.sourceMap)
      : undefined,
    // Keep what it can't parse and say so, instead of failing the build.
    errorRecovery: true,
    // Classes, ids and keyframes are scoped, as postcss-modules did; grid
    // and container names stay global. customIdents must stay on: without
    // it the @keyframes get renamed but `animation: spin` does not.
    cssModules: lane.cssModules
      ? { pattern: "[name]__[local]___[hash]", grid: false, container: false }
      : undefined,
  });

  const lines = result.css.split("\n");
  for (const { message, loc } of css.warnings) {
    // loc points into the compiled CSS; quote its line to make it findable.
    const source = lines[loc.line - 1]?.trim();
    warn(
      lane.id,
      `${entry}: ${message}${source ? ` in "${source.slice(0, 120)}"` : ""}`,
    );
  }

  let code = css.code.toString();
  if (css.map) {
    const map = relativeSources(JSON.parse(css.map.toString()), out);
    await writeIfChanged(`${out}.map`, JSON.stringify(map));
    code += `\n/*# sourceMappingURL=${path.basename(out)}.map */\n`;
  }
  await writeIfChanged(out, code);
  if (lane.asset) {
    // Same manifest as the JS bundles; PHP passes the version to
    // wp_register_style() (Shared\Build\AssetFile).
    await writeIfChanged(
      out.replace(/\.css$/, ".asset.php"),
      assetPhp({ dependencies: [], version: contentHash(code) }),
    );
  }

  if (lane.cssModules) {
    const map = moduleMaps.get(lane.cssModules) ?? new Map();
    moduleMaps.set(lane.cssModules, map);
    const names = Object.fromEntries(
      Object.entries(css.exports ?? {}).map(([local, { name }]) => [
        local,
        name,
      ]),
    );
    map.set(path.basename(entry, ".module.scss"), names);
  }
  return out;
}

/** modules.json: { "<block>": { "<local class>": "<scoped class>" } } */
async function writeModuleMaps() {
  for (const [file, map] of moduleMaps) {
    const sorted = Object.fromEntries(
      [...map].sort(([a], [b]) => a.localeCompare(b)),
    );
    await writeIfChanged(file, `${JSON.stringify(sorted, null, 2)}\n`);
  }
}

/** Compiles every entry of the CSS lanes. Resolves to the failure count. */
export async function buildCss(lanes, context) {
  let failures = 0;
  await Promise.all(
    lanes.map(async (lane) => {
      const start = performance.now();
      const entries = find(lane.include, lane.exclude);
      await Promise.all(
        entries.map((entry) =>
          compile(lane, entry, context).catch((error) => {
            failures++;
            fail(lane.id, sassError(error));
          }),
        ),
      );
      log(lane.id, `${entries.length} stylesheets ${since(start)}`);
    }),
  );
  await writeModuleMaps();
  return failures;
}

/**
 * Recompiles the entries a changed .scss file belongs to: the entry itself,
 * every entry whose last compile loaded it, and entries that never compiled
 * (their imports are unknown). New entries are found by globbing again.
 */
export async function rebuildCss(lanes, file, context) {
  const jobs = [];
  for (const lane of lanes) {
    for (const entry of find(lane.include, lane.exclude)) {
      const deps = graph.get(entry);
      if (entry !== file && deps && !deps.has(file)) continue;
      const start = performance.now();
      jobs.push(
        compile(lane, entry, context).then(
          (out) => log(lane.id, `${out} ${since(start)}`),
          (error) => fail(lane.id, sassError(error)),
        ),
      );
    }
  }
  await Promise.all(jobs);
  await writeModuleMaps();
  return jobs.length;
}
