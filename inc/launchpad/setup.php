<?php

/**
 * Setup for
 * Launchpad user admin panel app
 * Version: 0.7.5
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
});

// The Launchpad page: created on activation, protected from deletion.
// ManagedPage handles both, plus the case where the theme is deployed over
// Git or FTP and after_switch_theme never fires.
\Shared\Core\ManagedPage::register([
    'option'   => 'launchpad_page_id',
    'slug'     => 'launchpad',
    'title'    => static fn(): string => __('Launchpad', 'starwishx'),
    'template' => 'templates/page-launchpad.php',
]);

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

// Initialize Launchpad
add_action('after_setup_theme', function () {
    \launchpad();
}, 20);

// Register WP-CLI commands only when CLI is loaded.
if (defined('WP_CLI') && WP_CLI) {
    \Launchpad\Cli\MigrateOpportunityDatesCommand::register();
    update_option('starwish_run_opportunity_dates_migration', 1);
}
