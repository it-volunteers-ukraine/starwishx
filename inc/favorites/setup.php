<?php

/**
 * Setup for Favorites module
 *
 * Independent module for managing user favorites across the application.
 * Consumed by: Launchpad, Listing, Projects.
 * 
 * Version: 0.6.1
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 * 
 * Include from functions.php:
 * require_once get_template_directory() . '/inc/favorites/setup.php';
 */

namespace Favorites;

// Load helper functions first
require_once __DIR__ . '/helpers.php';

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Favorites\\';
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

// Initialize Favorites (before Launchpad/Listing/Projects)
add_action('after_setup_theme', function () {
    \favorites();
}, 15);
