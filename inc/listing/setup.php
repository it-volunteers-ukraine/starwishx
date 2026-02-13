<?php

/**
 * Setup for
 * Listing - Public Opportunities Discovery App
 * Version: 0.5.1
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * 
 * License: GPL v2 or later
 *
 * Include from functions.php:
 * require_once get_template_directory() . '/inc/listing/setup.php';
 */

namespace Listing;

// Load helper functions first
require_once __DIR__ . '/helpers.php';

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Listing\\';
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

    // Also create the Listing page
    $listing_page = get_page_by_path('listing');
    if (!$listing_page) {
        $page_id = wp_insert_post([
            'post_title'   => __('Listing Oportunities', 'starwishx'),
            'post_name'    => 'listing',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
            'post_author'  => 1,
            'meta_input'   => [
                '_wp_page_template' => 'templates/page-listing-oportunities.php',
            ],
        ]);

        if ($page_id && !is_wp_error($page_id)) {
            update_option('listing_page_id', $page_id);
        }
    }
});

//? this action here as dummie for future purposes
// Self healing trigger: Admin Pages Only
// Catches deployments via FTP/Git where theme wasn't switched
add_action('admin_init', function () {
    // Only run on admin pages (not frontend)
    // \Listing\Data\Migrations\MigrationManager::maybeRunMigrations();
});

// Prevent deletion of Listing page
add_action('before_delete_post', function ($post_id) {
    if ($post_id == get_option('listing_page_id')) {
        wp_die(__('The Listing page cannot be deleted.', 'starwishx'));
    }
});

// Prevent trashing of Listing page
add_action('wp_trash_post', function ($post_id) {
    if ($post_id == get_option('listing_page_id')) {
        wp_die(__('The Listing page cannot be trashed.', 'starwishx'));
    }
});

// Initialize Listing
add_action('after_setup_theme', function () {
    \listing();
}, 20);
