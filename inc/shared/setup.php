<?php

/**
 * Shared Infrastructure Setup
 *
 * Common classes and utilities used by multiple modules.
 */

// Autoloader for Shared namespace
spl_autoload_register(function (string $class): void {
    $prefix = 'Shared\\';
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
