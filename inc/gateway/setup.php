<?php

/**
 * Setup for
 * Gateway - user auth app
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * 
 * Include from functions.php:
 * require_once get_template_directory() . '/inc/gateway/setup.php';
 */

declare(strict_types=1);

namespace Gateway;

use Shared\Core\ManagedPage;

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

// The Gateway page: created on activation, protected from deletion.
// ManagedPage handles both, plus the case where the theme is deployed over
// Git or FTP and after_switch_theme never fires.
ManagedPage::register([
    'option'   => 'gateway_page_id',
    'slug'     => 'gateway',
    'title'    => static fn(): string => __('Gateway', 'starwishx'),
    'template' => 'templates/page-gateway.php',
]);

// Initialize Gateway singleton (priority 5, same as Launchpad)
add_action('after_setup_theme', function (): void {
    \gateway();
}, 5);
