<?php

/**
 * Social Share module — bootstrap
 *
 * Frontend-only module: renders a share popover on single CPT pages
 * (opportunity, news, project) via the Interactivity API.
 * No REST, no DB.
 *
 * File: inc/social-share/setup.php
 */

namespace SocialShare;

require_once __DIR__ . '/helpers.php';

spl_autoload_register(function ($class) {
    $prefix   = 'SocialShare\\';
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
    \social_share();
}, 15);
