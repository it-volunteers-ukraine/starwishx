<?php

/**
 * The router for PHP's built-in server in CI (`php -S … router.php`): what
 * exists on disk (assets, wp-login.php, wp-admin/) is served by the server
 * itself; everything else goes to WordPress, as a real server's
 * pretty-permalink rewrite would do.
 *
 * File: tests/e2e/support/router.php
 */

declare(strict_types=1);

$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if ($path !== '/' && file_exists((string) $_SERVER['DOCUMENT_ROOT'] . $path)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require (string) $_SERVER['DOCUMENT_ROOT'] . '/index.php';
