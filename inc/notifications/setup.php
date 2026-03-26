<?php

/**
 * Setup for Notifications module
 *
 * Async notification queue for comment events.
 * Delivers via email; extensible to Telegram and other channels.
 *
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * Include from functions.php:
 * require_once get_template_directory() . '/inc/notifications/setup.php';
 */

namespace Notifications;

// Load helper functions first
require_once __DIR__ . '/helpers.php';

// Autoloader
spl_autoload_register(function ($class) {
    $prefix   = 'Notifications\\';
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

// Migration triggers
add_action('after_switch_theme', function () {
    \Notifications\Data\Migrations\CreateNotificationTables::maybeRun();
});

add_action('admin_init', function () {
    \Notifications\Data\Migrations\CreateNotificationTables::maybeRun();
});

// Initialize Notifications (priority 12: after gateway, before comments)
add_action('after_setup_theme', function () {
    \notifications();
}, 12);
