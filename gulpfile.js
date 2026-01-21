import pkg from "gulp";
import postcss from "gulp-postcss";
import sourcemaps from "gulp-sourcemaps";
import autoprefixer from "autoprefixer";
import yargs from "yargs/yargs";
import { hideBin } from "yargs/helpers";
import gulpSass from "gulp-sass";
import * as sass from "sass";
import cleanCss from "gulp-clean-css";
import gulpif from "gulp-if";
import imagemin from "gulp-imagemin";
import { deleteAsync } from "del";
import webpack from "webpack-stream";
import named from "vinyl-named";
import replace from "gulp-replace";
import wpPot from "gulp-wp-pot";
import browserSync from "browser-sync";
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

const { src, dest, watch, series, parallel } = pkg;
const SASS = gulpSass(sass);
const argv = yargs(hideBin(process.argv)).argv;
const PRODUCTION = !!argv.prod;

let cssModulesJSON = {};

// BrowserSync
export const sync = () => {
  browserSync.init(config.localhost);
};

export const reload = (done) => {
  browserSync.reload();
  return done();
};

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
    .pipe(gulpif(PRODUCTION, postcss([autoprefixer])))
    .pipe(gulpif(PRODUCTION, cleanCss({ compatibility: "ie8" })))
    .pipe(gulpif(!PRODUCTION, sourcemaps.write()))
    .pipe(dest("assets/css"))
    .pipe(browserSync.stream());
};

export const templatesStyles = () => {
  return src(["src/scss/template-parts/*.scss"])
    .pipe(
      stylelint({
        fix: true,
        reporters: [{ formatter: "string", console: true }],
      })
    )
    .pipe(gulpif(!PRODUCTION, sourcemaps.init()))
    .pipe(SASS().on("error", SASS.logError))
    .pipe(gulpif(PRODUCTION, postcss([autoprefixer])))
    .pipe(gulpif(PRODUCTION, cleanCss({ compatibility: "ie8" })))
    .pipe(gulpif(!PRODUCTION, sourcemaps.write()))
    .pipe(dest("assets/css/template-parts"))
    .pipe(browserSync.stream());
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
    .pipe(gulpif(PRODUCTION, postcss([autoprefixer])))
    .pipe(gulpif(PRODUCTION, cleanCss({ compatibility: "ie8" })))
    .pipe(gulpif(!PRODUCTION, sourcemaps.write()))
    .pipe(dest("assets/css/blocks"))
    .pipe(browserSync.stream());
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

// Optimized Images with 'changed'
export const images = () => {
  return src(["src/img/**/*.{jpg,jpeg,png,gif,webp,avif}"], {
    allowEmpty: true,
    encoding: false,
  })
    .pipe(changed("assets/img")) // Skip if file hasn't changed
    .pipe(gulpif(PRODUCTION, imagemin()))
    .pipe(dest("assets/img"));
};

// for now just copy svgs
// .pipe(gulpif(PRODUCTION, imagemin()))
export const svgs = () => {
  return src("src/img/**/*.svg")
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
    };
  }

  return baseConfig;
};

export const scripts = () => {
  return src(["src/js/*.js"], { allowEmpty: true })
    .pipe(named())
    .pipe(webpack(webpackConfig(PRODUCTION)))
    .pipe(dest("assets/js"))
    .pipe(browserSync.stream());
};

export const vendorScripts = () => {
  return src(["src/js/vendor/*.js"], { allowEmpty: true })
    // .pipe(named())
    // .pipe(webpack(webpackConfig(PRODUCTION)))
    .pipe(dest("assets/js/vendor"))
    .pipe(browserSync.stream());
};

export const blockScripts = () => {
  return src(["inc/acf/blocks/**/*.js"], { allowEmpty: true })
    .pipe(named())
    .pipe(webpack(webpackConfig(PRODUCTION)))
    .pipe(dest("assets/js"))
    .pipe(browserSync.stream());
};

export const moduleScripts = () => {
  return src(
    [
      "inc/launchpad/Assets/*.js",
      "inc/launchpad/Assets/*.mjs",
      "inc/gateway/Assets/*.js",
      "inc/gateway/Assets/*.mjs",
    ],
    {
      allowEmpty: true,
    },
  )
    .pipe(named())
    .pipe(webpack(webpackConfig(PRODUCTION, true))) // true = ESM mode
    .pipe(rename({ suffix: ".module" }))
    .pipe(dest("assets/js"))
    .pipe(browserSync.stream());
};

export const pot = () => {
  return src("**/*.php", { allowEmpty: true })
    .pipe(wpPot({ domain: "_themedomain", package: config.theme.domain }))
    .pipe(dest(`languages/${config.theme.domain}.pot`));
};

export const production = () => {
  let version = "1.0.0"; // Default fallback

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
      "!assets/css/blocks/modules.json", // Optional: clean up build
      "!.babelrc",
      "!.gitignore",
      "!gulpfile*.js",
      "!package*.json",
      "!package-lock.json",
      "!README.md",
      "!config.js",
      "!.stylelintrc",
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

export const watchForChanges = () => {
  watch("src/scss/**/*.scss", styles);
  watch("src/scss/template-parts/*.scss", templatesStyles);
  watch("src/img/**/*.{jpg,jpeg,png,gif,webp,avif}", images);
  watch("src/img/**/*.svg", svgs);
  watch(
    ["src/**/*", "!src/{images,js,scss}", "!src/{images,js,scss}/**/*"],
    copy,
  );
  watch("src/js/**/*.js", scripts);
  watch("src/js/vendor/*.js", vendorScripts);
  watch("inc/acf/blocks/**/*.js", blockScripts);
  watch("inc/launchpad/Assets/**/*.{js,mjs}", moduleScripts);
  watch("inc/gateway/Assets/**/*.{js,mjs}", moduleScripts);
  watch("inc/acf/blocks/**/*.module.scss", blockStyles);
  watch("**/*.php", reload);
};

export const dev = series(
  clean,
  parallel(
    styles,
    templatesStyles,
    fonts,
    images,
    svgs,
    copy,
    scripts,
    vendorScripts,
    blockScripts,
    moduleScripts, // New task
    blockStyles,
  ),
  parallel(sync, watchForChanges),
);

export const build = series(
  clean,
  parallel(
    styles,
    templatesStyles,
    fonts,
    images,
    svgs,
    copy,
    scripts,
    vendorScripts,
    blockScripts,
    moduleScripts, // New task
    blockStyles,
  ),
  pot,
  production,
);

export default dev;
