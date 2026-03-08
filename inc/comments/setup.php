<?php

/**
 * Setup for Comments module
 *
 * Independent module for interactive comments/reviews on any post type.
 * Consumed by: single-opportunity, single-project templates.
 *
 * Version: 0.7.1
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * Include from functions.php:
 * require_once get_template_directory() . '/inc/comments/setup.php';
 */

namespace Comments;

// Load helper functions first
require_once __DIR__ . '/helpers.php';

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Comments\\';
    $base_dir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize Comments (before Launchpad, same priority as Favorites)
add_action('after_setup_theme', function () {
    \comments();
}, 15);
