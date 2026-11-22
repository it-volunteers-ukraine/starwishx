<?php

/**
 * Installs a throwaway WordPress for the E2E job: the site, the theme, the
 * plugins it needs, pretty permalinks and the theme's managed pages. CLI
 * only; wp-config.php must already point at a test database.
 *
 *   php install.php <wp-path> <url> <theme> [plugin/file.php …]
 *
 * Two passes: this one installs with WP_INSTALLING (no theme loaded), then
 * re-runs itself with --pages, where the theme loads and ManagedPage creates
 * /gateway/ and its other pages. Safe to run again on an installed site.
 *
 * File: tests/e2e/support/install.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

[, $e2ePath, $e2eUrl, $e2eTheme] = $argv + [null, '', '', ''];
$e2ePlugins = array_slice($argv, 4);
// The second pass (see the end): the theme is active and loaded.
$e2ePages = ($e2ePlugins !== [] && end($e2ePlugins) === '--pages');
$e2ePlugins = array_values(array_diff($e2ePlugins, ['--pages']));
$e2ePath = rtrim((string) $e2ePath, '/\\');

if (!is_file("{$e2ePath}/wp-config.php") || $e2eUrl === '' || $e2eTheme === '') {
    fwrite(STDERR, "usage: php install.php <wp-path> <url> <theme> [plugin/file.php …]\n");
    exit(2);
}

$e2eHost = (string) parse_url((string) $e2eUrl, PHP_URL_HOST);
$e2ePort = parse_url((string) $e2eUrl, PHP_URL_PORT);
$_SERVER['HTTP_HOST'] = $e2ePort ? "{$e2eHost}:{$e2ePort}" : $e2eHost;
$_SERVER['REQUEST_URI'] = '/';

if ($e2ePages) {
    // ManagedPage creates its pages on after_switch_theme or in wp-admin;
    // neither happens here, so ask it directly.
    require "{$e2ePath}/wp-load.php";
    if (!class_exists(\Shared\Core\ManagedPage::class)) {
        fwrite(STDERR, "install.php: the theme didn't load\n");
        exit(1);
    }
    \Shared\Core\ManagedPage::ensureAll();
    $e2eGateway = (int) get_option('gateway_page_id');
    if ($e2eGateway === 0) {
        fwrite(STDERR, "install.php: no gateway page\n");
        exit(1);
    }
    fwrite(STDOUT, "managed pages ready (gateway: {$e2eGateway})\n");
    exit(0);
}

define('WP_INSTALLING', true); // no theme or plugins load in this pass
require "{$e2ePath}/wp-load.php";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

if (str_contains((string) DB_NAME, 'test') === false) {
    fwrite(STDERR, "install.php: refusing a database without \"test\" in its name\n");
    exit(1);
}

if (!is_blog_installed()) {
    // The admin is never used, and isn't "e2e-" (cleanup deletes those).
    wp_install('StarWish E2E', 'site-admin', 'site-admin@example.com', false, '', wp_generate_password(24));
    fwrite(STDOUT, "installed WordPress {$GLOBALS['wp_version']}\n");
}

update_option('siteurl', $e2eUrl);
update_option('home', $e2eUrl);

foreach ($e2ePlugins as $e2ePlugin) {
    $e2eActivated = activate_plugin($e2ePlugin);
    if (is_wp_error($e2eActivated)) {
        fwrite(STDERR, "install.php: {$e2ePlugin}: {$e2eActivated->get_error_message()}\n");
        exit(1);
    }
    fwrite(STDOUT, "activated {$e2ePlugin}\n");
}

switch_theme($e2eTheme);
if (get_stylesheet() !== $e2eTheme) {
    fwrite(STDERR, "install.php: theme {$e2eTheme} not found\n");
    exit(1);
}
fwrite(STDOUT, "activated theme {$e2eTheme}\n");

update_option('permalink_structure', '/%postname%/');
delete_option('rewrite_rules'); // rebuilt on the first request
fwrite(STDOUT, "permalinks /%postname%/\n");

// The pages need the theme's code, which this pass doesn't load: run the
// second pass in a fresh process.
$e2eCommand = implode(' ', array_map('escapeshellarg', [
    PHP_BINARY, __FILE__, $e2ePath, $e2eUrl, $e2eTheme, '--pages',
]));
passthru($e2eCommand, $e2eStatus);
exit($e2eStatus);
