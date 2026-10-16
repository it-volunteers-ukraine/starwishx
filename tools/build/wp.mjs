/**
 * Rolldown plugin: WordPress dependencies of a bundle, and its .asset.php.
 *
 * Does what @wordpress/dependency-extraction-webpack-plugin did for webpack:
 * - "modules" lanes keep @wordpress/* (and the lane's own `external` map) as
 *   ESM imports, which WordPress' import map resolves.
 * - "scripts" lanes turn them into the wp.* globals of WordPress' script
 *   handles (wp.blockEditor = handle "wp-block-editor"), plus React & co.
 * - `asset: true` writes {name}.asset.php next to the bundle:
 *     array('dependencies' => array(...), 'version' => '<content hash>')
 *   Core reads it for block.json assets, PHP includes it for our own enqueues.
 *
 * File: tools/build/wp.mjs
 */

import { contentHash } from "./files.mjs";

const WP = "@wordpress/";

// Packages WordPress doesn't register as scripts - bundled instead, the same
// list the webpack plugin keeps.
const BUNDLED = new Set([
  "@wordpress/dataviews",
  "@wordpress/dataviews/wp",
  "@wordpress/fields",
  "@wordpress/icons",
  "@wordpress/interface",
  "@wordpress/sync",
  "@wordpress/undo-manager",
  "@wordpress/upload-media",
]);

// Non-WordPress packages that core ships as script handles.
const VENDOR = {
  react: { global: "React", handle: "react" },
  "react-dom": { global: "ReactDOM", handle: "react-dom" },
  "react/jsx-runtime": { global: "ReactJSXRuntime", handle: "react-jsx-runtime" },
  jquery: { global: "jQuery", handle: "jquery" },
  lodash: { global: "lodash", handle: "lodash" },
  "lodash-es": { global: "lodash", handle: "lodash" },
  moment: { global: "moment", handle: "moment" },
};

const camelCase = (name) => name.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());

/** "@wordpress/block-editor" -> "wp.blockEditor"; undefined when bundled. */
export function scriptGlobal(id) {
  if (Object.hasOwn(VENDOR, id)) return VENDOR[id].global;
  if (id.startsWith(WP) && !BUNDLED.has(id)) return `wp.${camelCase(id.slice(WP.length))}`;
  return undefined;
}

/** "@wordpress/block-editor" -> "wp-block-editor" */
function scriptHandle(id) {
  if (Object.hasOwn(VENDOR, id)) return VENDOR[id].handle;
  return `wp-${id.slice(WP.length)}`;
}

/** PHP literal in the layout json2php gives the webpack plugin's files. */
function php(value, depth = 0) {
  const pad = "\t".repeat(depth + 1);
  const end = "\t".repeat(depth);
  if (Array.isArray(value)) {
    if (!value.length) return "array()";
    return `array(\n${value.map((item) => pad + php(item, depth + 1)).join(",\n")}\n${end})`;
  }
  if (value && typeof value === "object") {
    const pairs = Object.entries(value).map(([key, item]) => `${pad}${php(key)} => ${php(item, depth + 1)}`);
    return `array(\n${pairs.join(",\n")}\n${end})`;
  }
  return `'${String(value).replace(/\\/g, "\\\\").replace(/'/g, "\\'")}'`;
}

/**
 * Dependencies of one entry chunk. Imports that aren't another chunk of
 * this build are externals - exactly the ids resolveId() kept out.
 */
function dependencies(lane, chunk, bundle) {
  const external = (id) => !Object.hasOwn(bundle, id);
  const byId = (a, b) => String(a.id ?? a).localeCompare(String(b.id ?? b));

  if (lane.wp === "modules") {
    const dynamic = chunk.dynamicImports.filter(external);
    return [
      ...chunk.imports.filter(external).filter((id) => !dynamic.includes(id)),
      // A dynamic import() becomes a module dependency WordPress fetches
      // on demand instead of preloading.
      ...dynamic.map((id) => ({ id, import: "dynamic" })),
    ].sort(byId);
  }
  return [...new Set(chunk.imports.filter(external).map(scriptHandle))].sort();
}

/**
 * The plugin for one lane; see tools/build/lanes.mjs for the lane shape.
 * `onWrite(file, ms)` is told about every bundle written (watch logging).
 */
export function wordpress(lane, onWrite) {
  const externals = lane.external ?? {};
  let started = 0;

  return {
    name: "starwishx:wordpress",

    buildStart() {
      started = performance.now();
    },

    writeBundle(options) {
      onWrite?.(options.file, Math.round(performance.now() - started));
    },

    resolveId(source) {
      if (Object.hasOwn(externals, source)) return { id: externals[source], external: true };
      if (lane.wp === "modules" && source.startsWith(WP) && !BUNDLED.has(source)) {
        return { id: source, external: true };
      }
      if (lane.wp === "scripts" && scriptGlobal(source)) return { id: source, external: true };
      return null;
    },

    generateBundle(_options, bundle) {
      if (!lane.asset) return;
      for (const chunk of Object.values(bundle)) {
        if (chunk.type !== "chunk" || !chunk.isEntry) continue;
        const asset = {
          dependencies: dependencies(lane, chunk, bundle),
          version: contentHash(chunk.code),
          ...(lane.wp === "modules" ? { type: "module" } : {}),
        };
        this.emitFile({
          type: "asset",
          fileName: chunk.fileName.replace(/\.js$/, ".asset.php"),
          source: `<?php return ${php(asset)};\n`,
        });
      }
    },
  };
}
