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

        // CSS: Intl-tel-input
        wp_enqueue_style(
            'intl-tel-input-css',
            'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css',
            [],
            '17.0.19'
        );

        // JS: Intl-tel-input Base
        wp_enqueue_script(
            'intl-tel-input-js',
            'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js',
            [],
            '17.0.19',
            true
        );

        // JS: Custom Contact Logic
        $js_path = __DIR__ . '/contact.js';
        wp_enqueue_script(
            'contact-form-js',
            get_template_directory_uri() . '/inc/acf/blocks/contact_form/contact.js',
            ['intl-tel-input-js'], // Ensures this loads AFTER the library
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
