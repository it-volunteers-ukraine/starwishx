<?php

/**
 * Handles block registration, AJAX handler loading, and asset management.
 * File: inc/acf/blocks/contact_form/register.php
 */

if (!defined('ABSPATH')) exit;

// Load the AJAX handler logic
require_once __DIR__ . '/contact-handler.php';

acf_register_block_type([
    'name'            => 'contact_form',
    'title'           => __('Contact Form', '_themedomain'),
    'description'     => __('Контактний блок з формою і контактною інформацією', '_themedomain'),
    'render_template' => __DIR__ . '/contact.php',
    'category'        => 'custom-blocks',
    'icon'            => 'email',

    'enqueue_assets'  => function () {

        // CSS: Module styles
        wp_enqueue_style(
            'contact-form-module-css',
            get_template_directory_uri() . '/assets/css/blocks/contact_form/contact.module.css',
            [],
            file_exists(get_template_directory() . '/assets/css/blocks/contact_form/contact.module.css')
                ? filemtime(get_template_directory() . '/assets/css/blocks/contact_form/contact.module.css')
                : '1.0.0'
        );

        // intlTelInput v26 CDN — same handles as LaunchpadCore (deduplicated by WP)
        wp_enqueue_style(
            'intl-tel-input',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@26.8.1/build/css/intlTelInput.css',
            [],
            '26.8.1'
        );
        wp_enqueue_script(
            'intl-tel-input',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@26.8.1/build/js/intlTelInputWithUtils.min.js',
            [],
            '26.8.1',
            true
        );

        // JS: Custom Contact Logic
        $js_path = __DIR__ . '/contact.js';
        wp_enqueue_script(
            'contact-form-js',
            get_template_directory_uri() . '/inc/acf/blocks/contact_form/contact.js',
            ['intl-tel-input'],
            file_exists($js_path) ? filemtime($js_path) : '1.0.0',
            true
        );

        // Localize: Pass AJAX URL and Nonce specifically to this script
        wp_localize_script('contact-form-js', 'ContactFormAjax', [
            'url'   => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('contact_form_nonce')
        ]);
    },
]);
