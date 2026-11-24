<?php

/**
 * PHPUnit bootstrap: the WordPress test suite (wp-phpunit) with this theme
 * active. Each test runs in a database transaction that is rolled back.
 *
 * File: tests/php/bootstrap.php
 */

declare(strict_types=1);

if (!extension_loaded('mysqli')) {
    fwrite(STDERR, sprintf(
        "tests/php: %s has no mysqli (php.ini: %s). Run the tests with a PHP that has it (e.g. Herd's).\n",
        PHP_BINARY,
        php_ini_loaded_file() ?: 'none'
    ));
    exit(1);
}

require __DIR__ . '/vendor/autoload.php'; // PHPUnit Polyfills, WP_PHPUNIT__DIR

putenv('WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php');

$wpTests = (string) getenv('WP_PHPUNIT__DIR');
require_once $wpTests . '/includes/functions.php';

/** The theme under test: the folder two levels up, whatever it's called. */
define('STARWISHX_TESTS_THEME_DIR', dirname(__DIR__, 2));

tests_add_filter('setup_theme', static function (): void {
    // In CI the theme isn't inside the downloaded core's wp-content/themes.
    register_theme_directory(dirname(STARWISHX_TESTS_THEME_DIR));
    search_theme_directories(true);
    switch_theme(basename(STARWISHX_TESTS_THEME_DIR));
});

// No real cookies from a test run (mail goes to the suite's MockPHPMailer),
// and no network: EmailPolicy's DNS probe is off (tests that need it turn it
// back on).
tests_add_filter('send_auth_cookies', '__return_false');
tests_add_filter('starwishx/email_dns_check', '__return_false');

require $wpTests . '/includes/bootstrap.php';
