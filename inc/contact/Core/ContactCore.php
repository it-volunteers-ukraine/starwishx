<?php

/**
 * Contact module — Core Singleton
 * Orchestrates: REST endpoint, Interactivity API assets & state, shortcode.
 * 
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * File: inc/contact/Core/ContactCore.php
 */

declare(strict_types=1);

namespace Contact\Core;

final class ContactCore
{
    public const MESSAGE_MAX_LENGTH = 500;

    private static ?self $instance = null;
    private bool $assetsLoaded     = false;

    public static function instance(): self
    {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_shortcode('starwish_contact', [$this, 'renderShortcode']);
    }

    /* ---------------------------------------------------------------
       REST
       --------------------------------------------------------------- */

    public function registerRestRoutes(): void
    {
        (new \Contact\Api\ContactController())->registerRoutes();
    }

    /* ---------------------------------------------------------------
       Assets
       --------------------------------------------------------------- */

    /**
     * Enqueue on matching page templates (hook: wp_enqueue_scripts).
     */
    public function enqueueAssets(): void
    {
        if (! $this->needsContactAssets()) {
            return;
        }

        $this->loadAssets();
    }

    /**
     * Determine whether the current request needs contact form assets.
     */
    private function needsContactAssets(): bool
    {
        return is_page_template('page-home.php')
            || is_page_template('page-contact.php');
    }

    /**
     * Register & enqueue intlTelInput + Interactivity module + state.
     * Idempotent — safe to call more than once (shortcode path).
     */
    public function loadAssets(): void
    {
        if ($this->assetsLoaded) {
            return;
        }

        $this->assetsLoaded = true;

        $this->enqueuePhoneAssets();

        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/contact',
                get_template_directory_uri() . '/assets/js/contact-store.module.js',
                ['@wordpress/interactivity']
            );
            wp_enqueue_script_module('@starwishx/contact');
        }

        $this->hydrateState();
    }

    /**
     * intlTelInput v26 — CDN handles shared with other modules (WP deduplicates).
     */
    private function enqueuePhoneAssets(): void
    {
        if (! wp_script_is('intl-tel-input', 'registered')) {
            wp_register_script(
                'intl-tel-input',
                'https://cdn.jsdelivr.net/npm/intl-tel-input@26.8.1/build/js/intlTelInputWithUtils.min.js',
                [],
                '26.8.1',
                true
            );
        }
        wp_enqueue_script('intl-tel-input');

        if (! wp_style_is('intl-tel-input', 'registered')) {
            wp_register_style(
                'intl-tel-input',
                'https://cdn.jsdelivr.net/npm/intl-tel-input@26.8.1/build/css/intlTelInput.css',
                [],
                '26.8.1'
            );
        }
        wp_enqueue_style('intl-tel-input');
    }

    /**
     * Hydrate Interactivity API state with infrastructure config & i18n.
     */
    private function hydrateState(): void
    {
        wp_interactivity_state('contact', [
            'config' => [
                'nonce'      => wp_create_nonce('wp_rest'),
                'restUrl'    => rest_url('contact/v1/'),
                'charLimit'  => self::MESSAGE_MAX_LENGTH,
                'spritePath' => get_template_directory_uri() . '/assets/img/sprites.svg',
                'messages'   => [
                    'required'     => __('Please fill in this field', 'starwishx'),
                    'invalidEmail' => __('Invalid email address', 'starwishx'),
                    'invalidPhone' => __('Invalid phone number', 'starwishx'),
                    'successTitle' => __('Thank you!', 'starwishx'),
                    'successText'  => __('Your message has been sent successfully.', 'starwishx'),
                    'errorText'    => __('Something went wrong. Please try again later.', 'starwishx'),
                ],
            ],
            'fields' => [
                'name'    => '',
                'phone'   => '',
                'email'   => '',
                'message' => '',
            ],
            'errors' => [
                'name'    => null,
                'phone'   => null,
                'email'   => null,
                'message' => null,
            ],
            'isSubmitting' => false,
            'showSuccess'  => false,
            'serverError'  => null,
        ]);
    }

    /* ---------------------------------------------------------------
       Shortcode  [starwish_contact]
       --------------------------------------------------------------- */

    /**
     * @param array|string $atts
     */
    public function renderShortcode($atts): string
    {
        $atts = shortcode_atts([
            'title_small'  => '',
            'title_medium' => '',
            'title_big'    => '',
            'subtitle'     => '',
        ], $atts, 'starwish_contact');

        $this->loadAssets();

        ob_start();
        get_template_part('template-parts/contact-section', null, $atts);

        return ob_get_clean();
    }
}
