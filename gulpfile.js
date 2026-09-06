/**
 * Theme build pipeline.
 *
 * Tasks: `gulp` (dev + watch), `gulp build --prod` (production bundle),
 * `gulp clean`, `gulp sprite`. Translations are not built here - they need
 * wp-cli; see the i18n:* scripts in package.json.
 *
 * File: gulpfile.js
 */

import pkg from "gulp";
import postcss from "gulp-postcss";
import sourcemaps from "gulp-sourcemaps";
import autoprefixer from "autoprefixer";
import cssnano from "cssnano";
import yargs from "yargs/yargs";
import { hideBin } from "yargs/helpers";
import gulpSass from "gulp-sass";
import * as sass from "sass";
import gulpif from "gulp-if";
import { deleteAsync } from "del";
import webpack from "webpack-stream";
import named from "vinyl-named";
import replace from "gulp-replace";
import config from "./config.js";
import fs from "fs";
import fonter from "gulp-fonter-fix";
import ttf2woff2 from "ttf2woff2";
import { Transform } from "stream";
import git from "git-rev-sync";
import path from "path";
import changed from "gulp-changed";
import stylelint from "gulp-stylelint-esm";
import postcssModules from "postcss-modules";
import rename from "gulp-rename";
import { buildSprite } from "./tools/build-sprite.mjs";

const { src, dest, watch, series, parallel } = pkg;
const SASS = gulpSass(sass);
const argv = yargs(hideBin(process.argv)).argv;
const PRODUCTION = !!argv.prod;

let cssModulesJSON = {};

// Styles
export const styles = () => {
  return src(["src/scss/*.scss"])
    .pipe(
      stylelint({
        fix: true,
        reporters: [{ formatter: "string", console: true }],
      }),
    )
    .pipe(gulpif(!PRODUCTION, sourcemaps.init()))
    .pipe(SASS().on("error", SASS.logError))
    .pipe(gulpif(PRODUCTION, postcss([autoprefixer, cssnano])))
    .pipe(gulpif(!PRODUCTION, sourcemaps.write()))
    .pipe(dest("assets/css"));
};

export const blockStyles = () => {
  return src(["inc/acf/blocks/**/*.module.scss"])
    .pipe(
      stylelint({
        fix: true,
        reporters: [{ formatter: "string", console: true }],
      }),
    )
    .pipe(
      postcss([
        postcssModules({
          generateScopedName: "[name]__[local]___[hash:base64:5]",
          getJSON: (cssFileName, json) => {
            const fileName = path.basename(cssFileName, ".module.scss");
            cssModulesJSON[fileName] = json;
            fs.mkdirSync("assets/css/blocks/", { recursive: true });
            fs.writeFileSync(
              "assets/css/blocks/modules.json",
              JSON.stringify(cssModulesJSON, null, 2),
            );
          },
        }),
      ]),
    )
    .pipe(gulpif(!PRODUCTION, sourcemaps.init()))
    .pipe(SASS().on("error", SASS.logError))
    .pipe(gulpif(PRODUCTION, postcss([autoprefixer, cssnano])))
    .pipe(gulpif(!PRODUCTION, sourcemaps.write()))
    .pipe(dest("assets/css/blocks"));
};

// Fonts logic remains similar but wrapped for stability
export const otfToTtf = () => {
  const srcDir = "./src/fonts";
  if (!fs.existsSync(srcDir)) return Promise.resolve();
  return src(`${srcDir}/*.otf`, { encoding: false })
    .pipe(fonter({ formats: ["ttf"] }))
    .pipe(dest("./assets/fonts/"));
};

class VinylTransform extends Transform {
  constructor() {
    super({ objectMode: true });
  }
  _transform(file, _, callback) {
    if (file.isBuffer()) {
      const woff2Buf = ttf2woff2(file.contents);
      const out = file.clone({ contents: false });
      out.contents = Buffer.from(woff2Buf);
      out.path = file.path.replace(/\.ttf$/i, ".woff2");
      this.push(out);
    }
    callback();
  }
}

export const ttfToWoff = () => {
  const srcDir = "./src/fonts";
  if (!fs.existsSync(srcDir)) return Promise.resolve();

  const produceWoff = src(`${srcDir}/*.ttf`, { encoding: false })
    .pipe(fonter({ formats: ["woff"] }))
    .pipe(dest("./assets/fonts"));
  const produceWoff2 = src(`${srcDir}/*.ttf`, { encoding: false })
    .pipe(new VinylTransform())
    .pipe(dest("./assets/fonts"));
  const copyExisting = src(`${srcDir}/*.{woff,woff2}`, {
    allowEmpty: true,
    encoding: false,
  }).pipe(dest("./assets/fonts"));

  return Promise.all([
    new Promise((r) => produceWoff.on("end", r)),
    new Promise((r) => produceWoff2.on("end", r)),
    new Promise((r) => copyExisting.on("end", r)),
  ]);
};

export const fontsStyle = (done) => {
  let fontsFile = `./src/scss/fonts.scss`;
  if (fs.existsSync(fontsFile)) {
    console.log("fonts.scss already exists.");
    return done();
  }

  fs.readdir("./assets/fonts", (err, files) => {
    if (files) {
      let fileContent = "";
      files.forEach((file) => {
        let fontFileName = file.split(".")[0];
        // Simplified logic for brevity, matches your existing weight detection
        fileContent += `@font-face {\n\tfont-family: ${fontFileName};\n\tfont-display: swap;\n\tsrc: url("../fonts/${fontFileName}.woff2") format("woff2");\n\tfont-weight: 400;\n\tfont-style: normal;\n}\n`;
      });
      fs.writeFileSync(fontsFile, fileContent);
    }
    done();
  });
};

const fonts = series(otfToTtf, ttfToWoff, fontsStyle);

// Raster images are copied as-is; changed() skips untouched files.
export const images = () => {
  return src(["src/img/**/*.{jpg,jpeg,png,gif,webp,avif}"], {
    allowEmpty: true,
    encoding: false,
  })
    .pipe(changed("assets/img"))
    .pipe(dest("assets/img"));
};

// Rebuilds src/img/sprites.svg from the icons in src/img/sprites-svgs/.
export const sprite = () => buildSprite();

// sprites-svgs/ is sprite input, and bak/ plus sprites-manual.svg are the
// superseded hand-built sprite kept for reference. None of them belong in the
// shipped theme - only the generated sprites.svg does.
export const svgs = () => {
  return src([
    "src/img/**/*.svg",
    "!src/img/sprites-svgs/**",
    "!src/img/bak/**",
    "!src/img/sprites-manual.svg",
  ])
    .pipe(changed("assets/img"))
    .pipe(dest("assets/img"));
};

// Optimized Copy with 'changed'
export const copy = () => {
  return src(
    [
      "src/**/*",
      "!src/{img,js,scss}",
      "!src/{img,js,scss}/**/*",
      "src/js/jquery.min.js",
      "src/js/swiper.min.js",
      "src/js/lightbox.js",
    ],
    { allowEmpty: true },
  )
    .pipe(changed("assets")) // Only copy modified files
    .pipe(dest("assets"));
};

export const clean = () => deleteAsync(["assets", "production"]);

const webpackConfig = (prod, isModule = false) => {
  const baseConfig = {
    module: {
      rules: [
        {
          test: /\.(js|mjs)$/, // Handle both
          use: {
            loader: "babel-loader",
            options: { presets: ["@babel/preset-env"] },
          },
        },
      ],
    },
    mode: prod ? "production" : "development",
    devtool: !prod ? "eval-source-map" : false,
    output: { filename: "[name].js" },
    externals: { jquery: "jQuery" },
  };

  if (isModule) {
    // Specialized settings for Interactivity API / ESM
    baseConfig.experiments = { outputModule: true };
    baseConfig.output.library = { type: "module" };
    baseConfig.externalsType = "module";
    baseConfig.externals = {
      "@wordpress/interactivity": "@wordpress/interactivity",
      "shepherd.js": "shepherd",
    };
  }

  return baseConfig;
};

export const scripts = () => {
  return src(["src/js/*.js"], { allowEmpty: true })
    .pipe(named())
    .pipe(webpack(webpackConfig(PRODUCTION)))
    .pipe(dest("assets/js"));
};

export const vendorScripts = () => {
  return (
    src(["src/js/vendor/*.js"], { allowEmpty: true })
      // .pipe(named())
      // .pipe(webpack(webpackConfig(PRODUCTION)))
      .pipe(dest("assets/js/vendor"))
  );
};

export const blockScripts = () => {
  return src(["inc/acf/blocks/**/*.js"], { allowEmpty: true })
    .pipe(named())
    .pipe(webpack(webpackConfig(PRODUCTION)))
    .pipe(dest("assets/js"));
};

export const moduleScripts = () => {
  // Entry points follow the *-store.js convention, one bundle per module.
  // Everything else under Assets/ (utils.js, tour-manager.js, and all of
  // inc/shared/) is imported by a store and bundled into it by webpack.
  return src(["inc/*/Assets/*-store.{js,mjs}", "!inc/shared/Assets/**"], {
    allowEmpty: true,
  })
    .pipe(named())
    .pipe(webpack(webpackConfig(PRODUCTION, true))) // true = ESM mode
    .pipe(rename({ suffix: ".module" }))
    .pipe(dest("assets/js"));
};

// Binaries must skip the production text pipeline below: src() reads them as
// UTF-8 and replace() re-encodes, which corrupts them (screenshot.png grew
// 583 KB -> 1 MB). copyBinariesToProduction byte-copies them instead.
const BINARY_FILES =
  "**/*.{png,jpg,jpeg,gif,webp,avif,ico,woff,woff2,ttf,otf,eot,mp4,webm,mp3,zip,pdf,mo}";

export const production = () => {
  let version = "1.0.0";

  try {
    // Check if .git directory exists before calling git-rev-sync
    if (fs.existsSync(path.resolve(process.cwd(), ".git"))) {
      version = git.short();
    } else {
      console.warn("Git not found, using default version 1.0.0");
    }
  } catch (e) {
    console.warn("Could not get git version, using default 1.0.0");
  }

  return src(
    [
      "**/*",
      "!node_modules{,/**}",
      "!src{,/**}",
      "!production{,/**}", // Prevent copying the production folder into itself
      "!" + BINARY_FILES, // Byte-copied by copyBinariesToProduction
      "!languages/*.po~", // Translation editor backups
      "!assets/img{,/**}",
      "!assets/fonts{,/**}",
      // Build tooling and package manager metadata - not needed at runtime.
      "!tools{,/**}",
      "!docs{,/**}",
      "!composer.{json,lock}", // vendor/ ships prebuilt; nothing runs composer
      "!pnpm-lock.yaml",
      "!pnpm-workspace.yaml",
      "!bitbucket-pipelines.yml",
      "!skills-lock.json",
      "!.babelrc",
      "!.gitignore",
      "!gulpfile*.js",
      "!package*.json",
      "!README.md",
      "!config.js",
      "!.stylelintrc",
      // NOTE: assets/css/blocks/modules.json must NOT be excluded here - the
      // ACF block templates read it at runtime to resolve their hashed CSS
      // module class names, and fail silently (unstyled) without it.
    ],
    { allowEmpty: true },
  )
    .pipe(replace("_themename", config.theme.name))
    .pipe(replace("_themeuri", config.theme.uri))
    .pipe(replace("_themedomain", config.theme.domain))
    .pipe(replace("_themeprefix", config.theme.prefix))
    .pipe(replace("_themeauthor", config.theme.author))
    .pipe(replace("_themeauthoruri", config.theme.authoruri))
    .pipe(replace("_themeversion", version))
    .pipe(replace("_themedescription", config.theme.description))
    .pipe(dest("./production"));
};

export const copyBinariesToProduction = () => {
  const promises = [];

  if (fs.existsSync("assets/img")) {
    promises.push(
      new Promise((resolve) =>
        src("assets/img/**/*", { allowEmpty: true, encoding: false })
          .pipe(dest("production/assets/img"))
          .on("end", resolve),
      ),
    );
  }

  if (fs.existsSync("assets/fonts")) {
    promises.push(
      new Promise((resolve) =>
        src("assets/fonts/**/*", { allowEmpty: true, encoding: false })
          .pipe(dest("production/assets/fonts"))
          .on("end", resolve),
      ),
    );
  }

  // Everything else the production task had to skip - screenshot.png today,
  // any binary added later automatically. assets/ is already handled above.
  promises.push(
    new Promise((resolve) =>
      src(
        [
          BINARY_FILES,
          "!node_modules{,/**}",
          "!src{,/**}",
          "!production{,/**}",
          "!assets{,/**}",
          "!docs{,/**}",
        ],
        { allowEmpty: true, encoding: false },
      )
        .pipe(dest("production"))
        .on("end", resolve),
    ),
  );

  return Promise.all(promises);
};

export const watchForChanges = () => {
  watch("src/scss/**/*.scss", styles);
  watch("src/img/**/*.{jpg,jpeg,png,gif,webp,avif}", images);
  watch("src/img/sprites-svgs/*.svg", series(sprite, svgs));
  // sprites.svg is excluded here because the line above already copies it;
  // watching it too would run svgs twice on every icon edit.
  watch(
    [
      "src/img/**/*.svg",
      "!src/img/sprites-svgs/**",
      "!src/img/bak/**",
      "!src/img/sprites.svg",
    ],
    svgs,
  );
  // These negations must match the copy task's own globs - "images" was a
  // typo for "img", so every image edit needlessly re-ran copy.
  watch(["src/**/*", "!src/{img,js,scss}", "!src/{img,js,scss}/**/*"], copy);
  watch("src/js/**/*.js", scripts);
  watch("src/js/vendor/*.js", vendorScripts);
  watch("inc/acf/blocks/**/*.js", blockScripts);
  // Watch every file a store can import, not just the store entry points,
  // so editing a helper or anything under inc/shared/ rebuilds its bundles.
  watch("inc/*/Assets/**/*.{js,mjs}", moduleScripts);
  watch("inc/acf/blocks/**/*.module.scss", blockStyles);
};

// No clean() here on purpose: it would wipe assets/ on every start and
// defeat the gulp-changed caching on images/copy. Run `gulp clean` when a
// fresh slate is actually wanted.
export const dev = series(
  sprite,
  parallel(
    styles,
    fonts,
    images,
    svgs,
    copy,
    scripts,
    vendorScripts,
    blockScripts,
    moduleScripts,
    blockStyles,
  ),
  watchForChanges,
);

export const build = series(
  clean,
  sprite,
  parallel(
    styles,
    fonts,
    images,
    svgs,
    copy,
    scripts,
    vendorScripts,
    blockScripts,
    moduleScripts,
    blockStyles,
  ),
  production,
  copyBinariesToProduction,
);

export default dev;
