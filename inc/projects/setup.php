<?php

/**
 * Setup for
 * Projects - Single Project Page
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 *
 * License: GPL v2 or later
 *
 * Include from functions.php:
 * require_once get_template_directory() . '/inc/projects/setup.php';
 */

declare(strict_types=1);

namespace Projects;

// Load helper functions first
require_once __DIR__ . '/helpers.php';

// PSR-4 style autoloader for Projects namespace
spl_autoload_register(function (string $class): void {
    $prefix = 'Projects\\';
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

// Projects owns no page and registers no rewrites - /projects/ is the CPT
// archive, served by archive-project.php through the template hierarchy.

// Initialize Projects
add_action('after_setup_theme', static function (): void {
    \projects();
}, 20);
