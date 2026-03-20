<?php

/**
 * Contact module — bootstrap
 *
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 * 
 * File: inc/contact/setup.php
 */

namespace Contact;

require_once __DIR__ . '/helpers.php';

spl_autoload_register(function ($class) {
    $prefix   = 'Contact\\';
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
    \contact();
}, 15);
