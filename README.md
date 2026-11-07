# Project StarWishX

## WordPress theme

### Requirements

- PHP 8.5.12+. This is enforced: below it, Composer's platform check stops the
  theme with a 500 on every request, so upgrade the server before deploying.
- MariaDB 11.4.13+ or MySQL 8.0.35+
- Node 26.13+ (LTS) and pnpm 12 to build (`engines` and `packageManager` in
  package.json)
- Composer 2.10+ to change PHP packages. `vendor/` is committed, and older
  versions regenerate it with code that PHP 8.5 deprecates.

## To start using the theme:

1. `pnpm install`
2. `pnpm dev` builds everything, then rebuilds whatever changes
3. `pnpm build` makes a clean production build and copies the deployable theme
   into `production/` (everything except what `.distignore` lists)

## Build

`tools/build.ts` builds all assets: the Interactivity API stores and block
scripts with Rolldown, the SCSS with Sass and then Lightning CSS (vendor
prefixes for the `browserslist` targets in package.json; minified by
`pnpm build`), plus the icon sprite and static files. Every output is declared
in `tools/build/lanes.ts` - a new store (`inc/<module>/Assets/<name>-store.js`)
or block (`inc/blocks/<slug>/view.js`, `editor.js`, `style.scss`) is picked up
without changing it. Node runs the TypeScript directly, so there's nothing to
compile: `tools/` sticks to syntax Node can strip (no enums or parameter
properties), and every import spells out its `.ts` extension.

- `pnpm clean` removes all build output.
- `node tools/build.ts --only=styles,stores` runs only some lanes.
- `pnpm dev` restarts itself when a file in `tools/` changes. It reads the
  `browserslist` once, at start: restart it by hand after changing that.
- Every script, script module and stylesheet the theme enqueues gets a
  `.asset.php` next to it (dependencies + content hash), the manifest
  `@wordpress/scripts` writes. Core reads it for block.json assets; the theme's
  own enqueues read it through `Shared\Build\AssetFile`, so a deploy busts
  exactly the files that changed.

## Checks

[Vite+](https://viteplus.dev) (`vite.config.ts`) lints the JS and TS with Oxlint,
type-checks `tools/`, and formats the JS, TS, JSON, YAML, Markdown and SCSS with
Oxfmt. Biome (`biome.jsonc`) lints the SCSS.

- `pnpm check` - format, lint and type check; `pnpm exec vp check --fix` applies
  fixes.
- `pnpm lint` - `pnpm check` plus Biome.
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
