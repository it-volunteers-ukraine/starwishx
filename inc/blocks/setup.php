<?php

/**
 * Blocks module — bootstrap
 *
 * Native (block.json) Gutenberg blocks. Every inc/blocks/{slug}/ folder that
 * contains a block.json is registered by BlocksCore. The folder is both source
 * and runtime: block.json, render.php and labels.php run in place, while gulp
 * compiles style.scss / view.js into inc/blocks/{slug}/build/ (gitignored),
 * which block.json references as "file:./build/…".
 *
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * File: inc/blocks/setup.php
 */

namespace Blocks;

require_once __DIR__ . '/helpers.php';

spl_autoload_register(function ($class) {
    $prefix   = 'Blocks\\';
    $base_dir = __DIR__ . '/';
    $len      = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file           = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

add_action('after_setup_theme', function () {
    \sw_blocks();
}, 15);
