<?php

/**
 * Setup for Tour module
 *
 * Guided onboarding tours for Launchpad using shepherd.js.
 * Injectable scenario system — external modules can register
 * their own tour definitions via the tour_register_scenarios action.
 *
 * Version: 0.1.0
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * Include from functions.php:
 * require_once get_template_directory() . '/inc/tour/setup.php';
 */

namespace Tour;

// Load helper functions first
require_once __DIR__ . '/helpers.php';

// Autoloader
spl_autoload_register(function ($class) {
    $prefix   = 'Tour\\';
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

// Initialize Tour (after launchpad=20)
add_action('after_setup_theme', function () {
    \tour();
}, 22);
