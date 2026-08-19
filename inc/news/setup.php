<?php

/**
 * News module — bootstrap
 *
 * File: inc/news/setup.php
 */

namespace News;

require_once __DIR__ . '/helpers.php';

spl_autoload_register(function ($class) {
    $prefix   = 'News\\';
    $base_dir = __DIR__ . '/';
    $len      = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file           = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

add_action('after_setup_theme', function () {
    \sw_news();
}, 15);
