# Project StarWishX

## WordPress theme

### Recommended to use:

- PHP 8.2
- MySQL 5.7

## To start using the theme:

1. `pnpm install` (Node 24.11+ and pnpm 12, see `engines` and `packageManager`
   in package.json)
2. `pnpm dev` builds everything, then rebuilds whatever changes
3. `pnpm build` makes a clean production build and copies the deployable theme
   into `production/` (everything except what `.distignore` lists)

## Build

`tools/build.mjs` builds all assets: the Interactivity API stores and block
scripts with Rolldown, the SCSS with Sass and then Lightning CSS (vendor
prefixes for the `browserslist` targets in package.json; minified by
`pnpm build`), plus the icon sprite and static files. Every output is declared
in `tools/build/lanes.mjs` - a new store (`inc/<module>/Assets/<name>-store.js`)
or block (`inc/blocks/<slug>/view.js`, `editor.js`, `style.scss`) is picked up
without changing it.

- `pnpm clean` removes all build output.
- `node tools/build.mjs --only=styles,stores` runs only some lanes.
- Every script, script module and stylesheet the theme enqueues gets a
  `.asset.php` next to it (dependencies + content hash), the manifest
  `@wordpress/scripts` writes. Core reads it for block.json assets; the theme's
  own enqueues read it through `Shared\Build\AssetFile`, so a deploy busts
  exactly the files that changed.

## Checks

[Vite+](https://viteplus.dev) (`vite.config.ts`) lints the JS with Oxlint and
formats JS, JSON, YAML and Markdown with Oxfmt; stylelint
(`stylelint.config.mjs`) checks the SCSS.

- `pnpm check` - format + lint check; `pnpm exec vp check --fix` applies fixes.
- `pnpm lint` - `pnpm check` plus stylelint.
- Before each commit, `vp staged` fixes and checks the staged files. The hook
  is installed by `pnpm install` (`vp config`); skip it once with
  `VP_GIT_HOOKS=0 git commit ...`.

## Icons

`src/img/sprites.svg` is generated - do not edit it by hand. Drop an icon into
`src/img/sprites-svgs/` and the filename becomes its symbol id
(`icon-heart.svg` -> `sw_svg('icon-heart')`). The sprite is rebuilt by `npm run
sprite`, and automatically by `npm run dev` and `npm run build`.

## Translations

`npm run i18n` regenerates the POT, merges it into `languages/uk.po`, and builds
both `uk.l10n.php` (used by WP 6.5+) and `uk.mo`. The individual steps are
`i18n:pot`, `i18n:po`, `i18n:php` and `i18n:mo`.

These need wp-cli, so **run them from the LocalWP site shell** (Local -> right
click the site -> Open site shell), where `wp` is on PATH. They are deliberately
not part of `npm run build`, because the CI images have no wp-cli.
