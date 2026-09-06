# Project StarWishX
## WordPress theme

### Recommended to use:
- PHP 8.2
- MySQL 5.7

## To start using the theme:
1. Copy the config-example.js file to config.js and substitute your values ​​there. This is only needed for development.
2. `npm i`
3. `npm run dev` to start working
4. `npm run build` to build production version of the theme, mostly used for BitBucket pipeline.

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
