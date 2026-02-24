<?php

/**
 * Setup for
 * Launchpad user admin panel app
 * Version: 0.5.1
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * 
 * License: GPL v2 or later
 *
 * Include from functions.php:
 * require_once get_template_directory() . '/inc/launchpad/setup.php';
 */

namespace Launchpad;

// Load helper functions first
require_once __DIR__ . '/helpers.php';

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Launchpad\\';
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

// Primary trigger: Theme Activation
// Runs ONCE when admin activates the theme
add_action('after_switch_theme', function () {

    \Launchpad\Data\Migrations\MigrationManager::maybeRunMigrations();
    // Also create the Launchpad page
    $launchpad_page = get_page_by_path('launchpad');
    if (!$launchpad_page) {
        $page_id = wp_insert_post([
            'post_title'   => __('Launchpad', 'starwishx'),
            'post_name'    => 'launchpad',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
            'post_author'  => 1,
            'meta_input'   => [
                '_wp_page_template' => 'templates/page-launchpad.php',
            ],
        ]);

        if ($page_id && !is_wp_error($page_id)) {
            update_option('launchpad_page_id', $page_id);
        }
    }
});

// Self healing trigger: Admin Pages Only
// Catches deployments via FTP/Git where theme wasn't switched
add_action('admin_init', function () {
    // Only run on admin pages (not frontend)
    \Launchpad\Data\Migrations\MigrationManager::maybeRunMigrations();
});

// Old: Run database migrations 
// add_action('init', function () {
//     if (\Launchpad\Data\Migrations\CreateLaunchpadTables::needsUpgrade()) {
//         \Launchpad\Data\Migrations\CreateLaunchpadTables::run();
//     }
// });

// Prevent deletion of Launchpad page
add_action('before_delete_post', function ($post_id) {
    if ($post_id == get_option('launchpad_page_id')) {
        wp_die(__('The Launchpad page cannot be deleted.', 'starwishx'));
    }
});

// Prevent trashing of Launchpad page
add_action('wp_trash_post', function ($post_id) {
    if ($post_id == get_option('launchpad_page_id')) {
        wp_die(__('The Launchpad page cannot be trashed.', 'starwishx'));
    }
});

// Initialize Launchpad
add_action('after_setup_theme', function () {
    \launchpad();
}, 20);
