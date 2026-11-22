<?php

/**
 * WordPress test suite configuration (read by wp-phpunit through the
 * WP_PHPUNIT__TESTS_CONFIG env var, set in bootstrap.php).
 *
 * Every value comes from an env var (CI) or, locally, from the gitignored
 * .env.local.php next to this file. No credentials are committed.
 *
 * THE TEST SUITE DROPS AND RECREATES EVERY `wptests_` TABLE IN ITS DATABASE.
 * It must never point at a site's database: the name must contain "test".
 *
 * File: tests/php/wp-tests-config.php
 */

declare(strict_types=1);

(static function (): void {
    $local = __DIR__ . '/.env.local.php';
    $file  = is_file($local) ? require $local : [];

    $setting = static function (string $name, ?string $default = null) use ($file): string {
        $value = getenv($name);
        if ($value === false) {
            $value = $file[$name] ?? $default;
        }
        if ($value === null) {
            fwrite(STDERR, "tests/php: set {$name} (env var, or tests/php/.env.local.php).\n");
            exit(1);
        }
        return (string) $value;
    };

    $dbName = $setting('WP_TESTS_DB_NAME');
    if (!str_contains(strtolower($dbName), 'test')) {
        fwrite(STDERR, "tests/php: refusing database \"{$dbName}\" - the test suite drops its tables; use a *test* database.\n");
        exit(1);
    }

    define('ABSPATH', rtrim($setting('WP_TESTS_ABSPATH'), '/\\') . '/');
    define('DB_NAME', $dbName);
    define('DB_USER', $setting('WP_TESTS_DB_USER', 'root'));
    define('DB_PASSWORD', $setting('WP_TESTS_DB_PASSWORD', ''));
    define('DB_HOST', $setting('WP_TESTS_DB_HOST', '127.0.0.1'));
})();

define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

define('WP_DEBUG', true);
define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'StarWish Tests');
// The suite installs WordPress in a child PHP process. Give it this
// process's php.ini: a shim (Herd's php.bat) may load one that the bare
// binary doesn't find, and without it there's no mysqli.
define('WP_PHP_BINARY', escapeshellarg(PHP_BINARY) . (
    php_ini_loaded_file() ? ' -c ' . escapeshellarg((string) php_ini_loaded_file()) : ''
));
define('WPLANG', '');

// Read by wp-phpunit's own wp-tests-config.php as well.
$table_prefix = 'wptests_';
