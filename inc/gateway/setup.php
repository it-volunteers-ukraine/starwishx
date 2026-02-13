<?php

/**
 * Setup for
 * Gateway - user auth app
 * Version: 0.5.1
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * 
 * Include from functions.php:
 * require_once get_template_directory() . '/inc/gateway/setup.php';
 */

declare(strict_types=1);

namespace Gateway;

// Load helper functions first
require_once __DIR__ . '/helpers.php';

// PSR-4 style autoloader for Gateway namespace
spl_autoload_register(function (string $class): void {
    $prefix = 'Gateway\\';
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

// Create Gateway page on theme activation
add_action('after_switch_theme', function (): void {
    $gateway_page = get_page_by_path('gateway');

    if ($gateway_page) {
        return;
    }

    $page_id = wp_insert_post([
        'post_title'   => __('Gateway', 'starwishx'),
        'post_name'    => 'gateway',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => '',
        'post_author'  => 1,
        'meta_input'   => [
            '_wp_page_template' => 'templates/page-gateway.php',
        ],
    ]);

    if ($page_id && !is_wp_error($page_id)) {
        update_option('gateway_page_id', $page_id);
    }
});

// Prevent deletion of Gateway page
add_action('before_delete_post', function (int $post_id): void {
    if ($post_id === (int) get_option('gateway_page_id')) {
        wp_die(__('The Gateway page cannot be deleted.', 'starwishx'));
    }
});

// Prevent trashing of Gateway page
add_action('wp_trash_post', function (int $post_id): void {
    if ($post_id === (int) get_option('gateway_page_id')) {
        wp_die(__('The Gateway page cannot be trashed.', 'starwishx'));
    }
});

// Initialize Gateway singleton (priority 5, same as Launchpad)
add_action('after_setup_theme', function (): void {
    \gateway();
}, 5);
