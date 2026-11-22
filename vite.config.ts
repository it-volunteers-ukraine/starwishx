/**
 * Vite+ (https://viteplus.dev): linting and type-checking (Oxlint), formatting
 * (Oxfmt) and the pre-commit checks on staged files. Nothing here builds anything - the
 * theme build is tools/build.ts, which doesn't depend on Vite+.
 *
 *   pnpm check            format + lint check (`vp check --fix` to apply)
 *   pnpm lint             the above plus Biome for the SCSS (biome.jsonc)
 *
 * File: vite.config.ts
 */

import { defineConfig } from "vite-plus";

// Generated, vendored or third-party code, and the legacy ACF blocks that
// are being replaced by inc/blocks (not worth reformatting on their way out).
const notOurs = [
  "assets/**",
  "production/**",
  "inc/blocks/*/build/**",
  "inc/acf/**",
  "src/js/vendor/**",
  "vendor/**",
  "tests/php/vendor/**",
  "node_modules/**",
  "docs/**",
  ".void/**",
];

export default defineConfig({
  lint: {
    // Type-checks (tsrs) the TypeScript projects - the build tools
    // (tsconfig.json), src/js (src/js/tsconfig.json) and the iAPI stores
    // (inc/tsconfig.json); each file takes its nearest tsconfig. Rules such
    // as no-floating-promises also reach the theme's JS through inferred
    // types. `pnpm typecheck` runs the TypeScript compiler on all three.
    options: { typeAware: true, typeCheck: true },
    ignorePatterns: notOurs,
    env: { browser: true, es2024: true },
    // inc/blocks/Assets/editor-media-attributes.js is a plain script on wp.*.
    globals: { wp: "readonly" },
    overrides: [
      {
        files: ["tools/**", "vite.config.ts"],
        env: { browser: false, node: true },
      },
      {
        // These read JSDoc as if it were real types, so in plain JS they
        // report loose comments ({Object}, untyped packages), not bugs.
        // TypeScript files have real types and keep them.
        files: ["inc/**/*.{js,mjs}", "src/**/*.js"],
        rules: {
          "typescript/no-misused-spread": "off",
          "typescript/no-redundant-type-constituents": "off",
          "typescript/no-useless-default-assignment": "off",
        },
      },
    ],
  },

  fmt: {
    // The code is wrapped at 80 columns (Oxfmt's default is 100).
    printWidth: 80,
    // package.json keeps its own key order.
    sortPackageJson: false,
    overrides: [
      {
        // Nested up to nine levels deep: 4 spaces, and the width to fit them.
        files: ["**/*.scss"],
        options: { tabWidth: 4, printWidth: 100 },
      },
    ],
    ignorePatterns: [
      ...notOurs,
      // Plain CSS is build output, vendored (src/css) or style.css, whose
      // header WordPress parses.
      "**/*.css",
      // Written by ACF and wp-cli.
      "acf-json/**",
      "languages/**",
      // Written by tests/php (UPDATE_FIXTURES=1).
      "tests/fixtures/**",
      "pnpm-lock.yaml",
      "composer.json",
      "composer.lock",
    ],
  },

  // `vp test` (Vitest, bundled with Vite+): the TS unit tests. The stores
  // run in Node against a stand-in Interactivity API (tests/ts/setup.ts).
  test: {
    include: ["tests/ts/**/*.test.ts"],
    environment: "node",
    setupFiles: ["tests/ts/setup.ts"],
  },

  staged: {
    "*.{js,mjs,ts,json,jsonc,md,yml,yaml,scss}": "vp check --fix",
    // No autofix: it should take a person to change the SCSS.
    "*.scss": "biome lint --error-on-warnings --no-errors-on-unmatched",
  },
});
