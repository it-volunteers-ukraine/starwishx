<?php

/**
 * Setup for Comments module
 *
 * Independent module for interactive comments/reviews on any post type.
 * Consumed by: single-opportunity, single-project templates.
 *
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * Include from functions.php:
 * require_once get_template_directory() . '/inc/comments/setup.php';
 */

declare(strict_types=1);

namespace Comments;

// Load helper functions first
require_once __DIR__ . '/helpers.php';

// PSR-4 for Comments\*: Comments\Api\CommentsController is Api/CommentsController.php
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, __NAMESPACE__ . '\\')) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen(__NAMESPACE__) + 1)) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// Initialize Comments (before Launchpad, same priority as Favorites)
add_action('after_setup_theme', static fn() => \comments(), 15);
