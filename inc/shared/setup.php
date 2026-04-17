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

// Normalise WP core's rest_invalid_param errors into our field_errors
// contract so arg-level validate_callbacks reach the inline UI surface
// instead of the generic "Invalid parameter(s): X" banner. Hooked at the
// earliest REST init point so every subsequently-registered route inherits
// the rewrite.
add_action('rest_api_init', [\Shared\Core\AbstractApiController::class, 'bootErrorShapeFilter'], 0);
