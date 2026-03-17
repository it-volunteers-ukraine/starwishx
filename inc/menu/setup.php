<?php

/**
 * Setup for
 * Menu - Header Navigation Module
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * 
 * License: GPL v2 or later
 *
 * File: inc/menu/setup.php
 */

namespace Menu;

require_once __DIR__ . '/helpers.php';

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Menu\\';
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

// Initialize Menu
add_action('after_setup_theme', function () {
    \menu();
}, 10);
