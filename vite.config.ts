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
  "node_modules/**",
  "docs/**",
  ".void/**",
];

export default defineConfig({
  lint: {
    // Type-checks what tsconfig.json includes (the build tools). Rules such as
    // no-floating-promises also reach the theme's JS through inferred types.
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
        files: ["inc/**", "src/**"],
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
    ignorePatterns: [
      ...notOurs,
      // Plain CSS is build output, vendored (src/css) or style.css, whose
      // header WordPress parses.
      "**/*.css",
      // Written by ACF and wp-cli.
      "acf-json/**",
      "languages/**",
      "pnpm-lock.yaml",
      "composer.json",
      "composer.lock",
    ],
  },

  staged: {
    "*.{js,mjs,ts,json,jsonc,md,yml,yaml,scss}": "vp check --fix",
    // No autofix: it should take a person to change the SCSS.
    "*.scss": "biome lint --error-on-warnings --no-errors-on-unmatched",
  },
});
