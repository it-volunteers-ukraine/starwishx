<?php

/**
 * Setup for Chat module
 *
 * Notification center & support messaging panel for Launchpad.
 * Reads from sw_notifications (owned by Notifications module)
 * and renders an activity feed inside the user dashboard.
 *
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * Include from functions.php:
 * require_once get_template_directory() . '/inc/chat/setup.php';
 */

namespace Chat;

// Load helper functions first
require_once __DIR__ . '/helpers.php';

// Autoloader
spl_autoload_register(function ($class) {
    $prefix   = 'Chat\\';
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

// Initialize Chat (after notifications=12, before launchpad=20)
add_action('after_setup_theme', function () {
    \chat();
}, 18);
