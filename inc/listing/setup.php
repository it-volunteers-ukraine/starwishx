<?php

/**
 * Setup for
 * Listing - Public Opportunities Discovery App
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 *
 * License: GPL v2 or later
 *
 * Include from functions.php:
 * require_once get_template_directory() . '/inc/listing/setup.php';
 */

declare(strict_types=1);

namespace Listing;

use Shared\Core\ManagedPage;

// Load helper functions first
require_once __DIR__ . '/helpers.php';

// PSR-4 style autoloader for Listing namespace
spl_autoload_register(function (string $class): void {
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

/**
 * The /opportunities/ page.
 *
 * The URL itself belongs to the `opportunity` post type, which declares
 * has_archive_slug "opportunities" - so WordPress routes /opportunities/ to
 * archive-opportunity.php and this page never renders. It is kept purely so
 * plugins that expect a real post object for the section have one to find,
 * which is also why it carries no template.
 */
ManagedPage::register([
    'option'        => 'sw_opportunities_page_id',
    'slug'          => 'opportunities',
    'title'         => static fn(): string => __('Opportunities', 'starwishx'),
    'legacy_option' => 'listing_page_id',
]);

// Initialize Listing
add_action('after_setup_theme', static function (): void {
    \listing();
}, 20);
