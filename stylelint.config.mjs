/**
 * Stylelint for the theme's SCSS (src/scss, inc/blocks). Runs on staged
 * files before each commit (vp staged, see vite.config.ts) and through
 * `pnpm style:lint` - not inside the build.
 *
 * File: stylelint.config.mjs
 */

// These rules stay on as warnings: on 2026-10-16 they flagged 15 places
// written before stylelint was enforced (camelCase keyframe/container/function
// names, four shorthand-overrides-longhand declarations, an empty block, a
// deprecated property, an @extend of a class). Each needs a rename or a design
// decision rather than an autofix, and as errors they would block every
// commit touching those files. Patterns are the stylelint-config-standard(-scss)
// defaults.
const backlog = { severity: "warning" };

/** @type {import("stylelint").Config} */
export default {
  extends: ["stylelint-config-standard-scss"],
  rules: {
    "selector-class-pattern": [
      "^[a-z0-9]+([a-z0-9_-]*[a-z0-9]+)?$",
      { message: "Classnames must be in kebab-case or BEM notation." },
    ],
    "media-feature-range-notation": ["prefix", { disableFix: true }],

    "keyframes-name-pattern": ["^([a-z][a-z0-9]*)(-[a-z0-9]+)*$", backlog],
    "container-name-pattern": ["^(--)?([a-z][a-z0-9]*)(-[a-z0-9]+)*$", backlog],
    "scss/at-function-pattern": ["^(-?[a-z][a-z0-9]*)(-[a-z0-9]+)*$", backlog],
    "declaration-block-no-shorthand-property-overrides": [true, backlog],
    "block-no-empty": [true, backlog],
    "property-no-deprecated": [true, backlog],
    "scss/at-extend-no-missing-placeholder": [true, backlog],
  },
};
