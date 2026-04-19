<?php

/**
 * Setup for Users module.
 *
 * Owns user-lifecycle state: is_activated (registration → activation),
 * moderation_status (future), and the inactive-account cleanup cron.
 *
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * Include from functions.php (before Gateway):
 * require_once get_template_directory() . '/inc/users/setup.php';
 */

declare(strict_types=1);

namespace Users;

// Load helper function first so function_exists('users') becomes true immediately.
require_once __DIR__ . '/helpers.php';

// PSR-4 style autoloader for Users namespace
spl_autoload_register(function (string $class): void {
    $prefix = 'Users\\';
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

// Initialize Users singleton BEFORE Gateway (priority 5) so hook registration
// precedes any user_register / after_password_reset / login events Gateway triggers.
add_action('after_setup_theme', function (): void {
    \users();
}, 3);
