/**
 * Every output of the theme build, declared as data - tools/build.mjs runs
 * them. A new store, block or stylesheet that follows the conventions below
 * is picked up without touching this file. Paths are theme-relative globs.
 *
 * type "js" - Rolldown, one bundle per entry. Each bundle is self-contained,
 *   the way WordPress registers every script and script module under its
 *   own handle.
 *     wp: "modules" - @wordpress/* stay ESM imports, resolved by WordPress'
 *                     import map (Interactivity API stores, view modules)
 *     wp: "scripts" - @wordpress/* become the wp.* globals of WordPress'
 *                     script handles (block editor scripts)
 *     asset: true   - also write {name}.asset.php: dependencies + content-hash
 *                     version, the manifest core reads for block.json assets
 * type "css"  - sass-embedded, then Lightning CSS (prefixes, minify, modules).
 * type "sprite" - tools/build-sprite.mjs, before the files lane copies it.
 * type "copy" - static files copied as-is, only when changed.
 *
 * legacy: true marks the ACF-era lanes and Swiper's vendor copies - delete
 * them once inc/acf/blocks and Swiper are gone.
 *
 * File: tools/build/lanes.mjs
 */

import path from "node:path";

const name = (file) => path.basename(file, path.extname(file));
const slug = (file) => path.basename(path.dirname(file));

export const lanes = [
  {
    id: "stores",
    type: "js",
    // Entry points follow the *-store.js convention, one bundle per module.
    // Everything else under Assets/ (utils.js, tour-manager.js, and all of
    // inc/shared/Assets) is imported by a store and bundled into it.
    include: ["inc/*/Assets/*-store.{js,mjs}"],
    exclude: ["inc/shared/Assets/**"],
    out: (file) => `assets/js/${name(file)}.module.js`,
    format: "es",
    wp: "modules",
    // TourCore registers Shepherd from its CDN as the script module "shepherd".
    external: { "shepherd.js": "shepherd" },
  },
  {
    id: "scripts",
    type: "js",
    // Classic deferred scripts. `_*.js` are partials that app.js imports (one
    // feature each), the same convention as Sass partials.
    include: ["src/js/*.js"],
    exclude: ["src/js/_*.js"],
    out: (file) => `assets/js/${name(file)}.js`,
    format: "iife",
  },
  {
    id: "block-view",
    type: "js",
    // block.json "viewScriptModule": file:./build/view.js. Without the
    // .asset.php core registers the module with no dependencies, and a bare
    // `import "@wordpress/interactivity"` only works when some other store
    // happened to put the runtime into the import map.
    include: ["inc/blocks/*/view.js"],
    out: (file) => `inc/blocks/${slug(file)}/build/view.js`,
    format: "es",
    wp: "modules",
    asset: true,
  },
  {
    id: "block-editor",
    type: "js",
    // block.json "editorScript": file:./build/editor.js (JSX, InnerBlocks,
    // RichText). Imports from editor/*.js are bundled in.
    include: ["inc/blocks/*/editor.js"],
    out: (file) => `inc/blocks/${slug(file)}/build/editor.js`,
    format: "iife",
    wp: "scripts",
    jsx: true,
    asset: true,
  },
  {
    id: "acf-scripts",
    type: "js",
    legacy: true,
    include: ["inc/acf/blocks/**/*.js"],
    out: (file) => `assets/js/${name(file)}.js`,
    format: "iife",
  },
  {
    id: "styles",
    type: "css",
    include: ["src/scss/*.scss"],
    exclude: ["src/scss/_*.scss"],
    out: (file) => `assets/css/${name(file)}.css`,
  },
  {
    id: "block-styles",
    type: "css",
    // block.json "style" / "editorStyle": file:./build/style.css.
    include: ["inc/blocks/*/{style,editor}.scss"],
    out: (file) => `inc/blocks/${slug(file)}/build/${name(file)}.css`,
    // Lets block SCSS write `@use "variables" as *;` instead of
    // ../../../../src/scss chains.
    loadPaths: ["src/scss"],
  },
  {
    id: "acf-styles",
    type: "css",
    legacy: true,
    include: ["inc/acf/blocks/**/*.module.scss"],
    out: (file) => `assets/css/blocks/${file.slice("inc/acf/blocks/".length).replace(/\.scss$/, ".css")}`,
    // The ACF block templates read this map at runtime to resolve their
    // hashed class names, and render unstyled without it.
    cssModules: "assets/css/blocks/modules.json",
  },
  {
    id: "sprite",
    type: "sprite",
    // Icon sources; the generated src/img/sprites.svg is copied by "files".
    include: ["src/img/sprites-svgs/*.svg"],
  },
  {
    id: "files",
    type: "copy",
    sets: [
      {
        // sprites-svgs/ is sprite input, and bak/ plus sprites-manual.svg are
        // the superseded hand-built sprite kept for reference. None of them
        // belong in the shipped theme - only the generated sprites.svg does.
        include: ["src/img/**/*.{jpg,jpeg,png,gif,webp,avif,svg}"],
        exclude: ["src/img/sprites-svgs/**", "src/img/bak/**", "src/img/sprites-manual.svg"],
        to: (file) => file.replace(/^src\/img\//, "assets/img/"),
      },
      {
        // Swiper's stylesheet (legacy acf/projects block only).
        legacy: true,
        include: ["src/css/**/*"],
        to: (file) => file.replace(/^src\//, "assets/"),
      },
      {
        // Swiper's script, shipped prebuilt (legacy acf/projects block only).
        legacy: true,
        include: ["src/js/vendor/*.js"],
        to: (file) => file.replace(/^src\/js\//, "assets/js/"),
      },
    ],
  },
];

/** Directories every build writes into - removed by --clean and --prod. */
export const outputDirs = ["assets", "inc/blocks/*/build"];
