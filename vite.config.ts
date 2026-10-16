/**
 * Vite+ (https://viteplus.dev): linting (Oxlint), formatting (Oxfmt) and the
 * pre-commit checks on staged files. Nothing here builds anything - the
 * theme build is tools/build.mjs, which doesn't depend on Vite+.
 *
 *   pnpm check            format + lint check (`vp check --fix` to apply)
 *   pnpm lint             the above plus stylelint for the SCSS
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
    ignorePatterns: notOurs,
    env: { browser: true, es2024: true },
    // inc/blocks/Assets/editor-media-attributes.js is a plain script on wp.*.
    globals: { wp: "readonly" },
    overrides: [
      {
        files: ["tools/**", "vite.config.ts"],
        env: { browser: false, node: true },
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
      // Mixed 2- and 4-space indentation; stylelint covers the SCSS for now.
      // Plain CSS is build output, vendored (src/css) or style.css, whose
      // header WordPress parses.
      "**/*.scss",
      "**/*.css",
      // Written by ACF and wp-cli.
      "acf-json/**",
      "languages/**",
      "pnpm-lock.yaml",
      "composer.json",
      "composer.lock",
      "bitbucket-pipelines.yml",
    ],
  },

  staged: {
    "*.{js,mjs,ts,json,md,yml,yaml}": "vp check --fix",
    "*.scss": "stylelint --fix",
  },
});
