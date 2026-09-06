/**
 * Generates src/img/sprites.svg from the individual icons in
 * src/img/sprites-svgs/. Each file becomes one <symbol>, with its
 * filename stem as the symbol id (e.g. icon-heart.svg -> #icon-heart).
 *
 * Icons are optimized with svgo. Internal ids (gradients, clipPaths) are
 * namespaced per icon, otherwise the several icons that all ship an
 * id="a" would collide once concatenated into a single document.
 *
 * Usage: node tools/build-sprite.mjs
 *        (also invoked by the `sprite` gulp task and `npm run sprite`)
 *
 * File: tools/build-sprite.mjs
 */

import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { optimize } from "svgo";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const SRC_DIR = path.join(ROOT, "src", "img", "sprites-svgs");
const OUT_FILE = path.join(ROOT, "src", "img", "sprites.svg");

// Root <svg> attributes worth carrying onto the <symbol>. Presentation
// attributes inherit into the shadow tree, so dropping them would change
// how icons render; width/height/xmlns/class are meaningless on a symbol.
const KEEP_ATTRS = [
  "viewBox",
  "fill",
  "stroke",
  "stroke-width",
  "stroke-linecap",
  "stroke-linejoin",
  "fill-rule",
  "clip-rule",
];

// svgo 4 keeps viewBox by default (removeViewBox is no longer part of
// preset-default), which is what a <symbol> needs to scale. The buildSprite
// loop asserts every icon still has one, so an svgo upgrade that changes
// this fails the build instead of silently shipping broken icons.
const svgoConfig = (prefix) => ({
  multipass: true,
  plugins: [
    "preset-default",
    // width/height on a symbol are ignored; viewBox carries the geometry.
    "removeDimensions",
    // Must run last: namespaces every id and url(#...) reference per icon so
    // the icons that each declare an id="a" stop colliding once merged.
    {
      name: "prefixIds",
      params: { prefix, delim: "__", prefixIds: true, prefixClassNames: true },
    },
  ],
});

/** Pull the root <svg ...> attributes and inner markup out of an SVG string. */
const splitRoot = (svg) => {
  const open = svg.indexOf("<svg");
  const gt = svg.indexOf(">", open);
  const close = svg.lastIndexOf("</svg>");
  if (open === -1 || gt === -1 || close === -1) return null;

  const attrs = {};
  const attrRe = /([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*"([^"]*)"/g;
  let m;
  while ((m = attrRe.exec(svg.slice(open + 4, gt))) !== null) {
    attrs[m[1]] = m[2];
  }

  return { attrs, body: svg.slice(gt + 1, close).trim() };
};

export async function buildSprite({ quiet = false } = {}) {
  const files = (await fs.readdir(SRC_DIR))
    .filter((f) => f.toLowerCase().endsWith(".svg"))
    .sort();

  if (files.length === 0) {
    throw new Error(`No SVG files found in ${SRC_DIR}`);
  }

  let rawBytes = 0;
  const symbols = [];

  for (const file of files) {
    const id = path.basename(file, path.extname(file));
    const raw = await fs.readFile(path.join(SRC_DIR, file), "utf8");
    rawBytes += Buffer.byteLength(raw);

    const { data } = optimize(raw, {
      path: file,
      ...svgoConfig(id),
    });

    const parsed = splitRoot(data);
    if (!parsed) {
      throw new Error(`Could not parse root <svg> in ${file}`);
    }
    if (!parsed.attrs.viewBox) {
      throw new Error(`${file} has no viewBox - it cannot become a <symbol>`);
    }

    const attrs = KEEP_ATTRS.filter((name) => parsed.attrs[name] !== undefined)
      .map((name) => `${name}="${parsed.attrs[name]}"`)
      .join(" ");

    symbols.push(`  <symbol id="${id}" ${attrs}>${parsed.body}</symbol>`);
  }

  const out = [
    '<svg width="0" height="0" fill="none" style="visibility: hidden; position: absolute;" aria-hidden="true"',
    '    xmlns="http://www.w3.org/2000/svg">',
    ...symbols,
    "</svg>",
    "",
  ].join("\n");

  let previous = 0;
  try {
    previous = (await fs.stat(OUT_FILE)).size;
  } catch {
    // First run - no existing sprite to compare against.
  }

  await fs.writeFile(OUT_FILE, out, "utf8");

  if (!quiet) {
    const kb = (n) => `${(n / 1024).toFixed(1)} KB`;
    console.log(
      `[sprite] ${files.length} icons -> src/img/sprites.svg  ` +
        `${kb(rawBytes)} raw -> ${kb(Buffer.byteLength(out))}` +
        (previous ? ` (was ${kb(previous)})` : ""),
    );
  }

  return { count: files.length, bytes: Buffer.byteLength(out) };
}

// Run directly: node tools/build-sprite.mjs
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  buildSprite().catch((err) => {
    console.error(`[sprite] ${err.message}`);
    process.exitCode = 1;
  });
}
