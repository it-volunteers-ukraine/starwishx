<?php

/**
 * Gateway - user auth app
 * Version: 0.5.1
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 * 
 */

// File: inc/gateway/Core/GatewayCore.php

declare(strict_types=1);

namespace Gateway\Core;

use Gateway\Api\AuthController;
use Gateway\Api\RegisterController;
use Gateway\Api\PasswordController;

/**
 * Main Gateway singleton.
 * Mirrors LaunchpadCore architecture.
 */
final class GatewayCore
{
    private static ?self $instance = null;
    private FormRegistry $registry;
    private StateAggregator $stateAggregator;
    private RedirectHandler $redirectHandler;

    /**
     * Get singleton instance with optional DI for testing.
     */
    public static function instance(
        ?FormRegistry $registry = null,
        ?StateAggregator $aggregator = null,
        ?RedirectHandler $redirect = null
    ): self {
        if (!self::$instance) {
            self::$instance = new self(
                $registry ?? new FormRegistry(),
                $aggregator ?? new StateAggregator(),
                $redirect ?? new RedirectHandler()
            );
        }
        return self::$instance;
    }

    /**
     * Reset singleton (for testing only).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    private function __construct(
        FormRegistry $registry,
        StateAggregator $aggregator,
        RedirectHandler $redirect
    ) {
        $this->registry = $registry;
        $this->stateAggregator = $aggregator;
        $this->redirectHandler = $redirect;

        $this->bootstrap();
    }

    /**
     * Register all WordPress hooks.
     *
     * CRITICAL: Hook registration order matters!
     * Follow Launchpad's proven pattern.
     */
    private function bootstrap(): void
    {
        // LOGIN INTERCEPTION
        // login_init fires when wp-login.php loads, before any output.
        // This is THE hook to intercept and redirect.
        add_action('login_init', [$this->redirectHandler, 'interceptWpLogin']);

        // URL FILTERS
        // These ensure any theme/plugin calling wp_login_url() etc.
        // gets our Gateway URLs instead of wp-login.php
        add_filter('login_url', [$this->redirectHandler, 'filterLoginUrl'], 10, 2);
        add_filter('register_url', [$this->redirectHandler, 'filterRegisterUrl']);
        add_filter('lostpassword_url', [$this->redirectHandler, 'filterLostPasswordUrl']);
        add_filter('logout_url', [$this->redirectHandler, 'filterLogoutUrl'], 10, 2);

        // FRONTEND ASSETS
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // REST API
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // FORM REGISTRATION (Extensibility)
        // Fire custom action at init priority 20 (after plugins loaded)
        add_action('init', fn() => do_action('gateway_register_forms', $this->registry), 20);

        // Register default forms at priority 5 (before third-party)
        add_action('gateway_register_forms', [$this, 'registerDefaultForms'], 5);
    }

    /**
     * Register built-in forms.
     */
    public function registerDefaultForms(FormRegistry $registry): void
    {
        $registry->register('login', new \Gateway\Forms\LoginForm(), 10);
        $registry->register('register', new \Gateway\Forms\RegisterForm(), 20);
        $registry->register('lost-password', new \Gateway\Forms\LostPasswordForm(), 30);
        $registry->register('reset-password', new \Gateway\Forms\ResetPasswordForm(), 40);
    }

    /**
     * Enqueue assets only on Gateway page.
     * Mirrors LaunchpadCore::enqueueAssets()
     */
    public function enqueueAssets(): void
    {
        if (!is_page('gateway')) {
            return;
        }

        // Load asset manifest
        $asset_path = get_template_directory() . '/inc/gateway/Assets/gateway-store.asset.php';
        $asset = file_exists($asset_path)
            ? include $asset_path
            : ['dependencies' => [], 'version' => '1.0.0'];

        // Register ES module
        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/gateway',
                // get_template_directory_uri() . '/assets/js/gateway-store.module.js',
                get_template_directory_uri() . '/inc/gateway/Assets/gateway-store.js',
                array_merge(['@wordpress/interactivity'], $asset['dependencies']),
                $asset['version']
            );
            wp_enqueue_script_module('@starwishx/gateway');
        }

        // Inject settings for JS
        wp_add_inline_script(
            'wp-interactivity',
            sprintf('window.gatewaySettings = %s;', wp_json_encode([
                'nonce'   => wp_create_nonce('wp_rest'),
                'restUrl' => rest_url('gateway/v1/'),
                'baseUrl' => home_url('/gateway/'), // NEW: Supports subdirectory installations
            ])),
            'before'
        );
    }

    /**
     * Register REST API controllers.
     */
    public function registerRestRoutes(): void
    {
        $controllers = [
            new AuthController(),
            new RegisterController(),
            new PasswordController(),
        ];

        foreach ($controllers as $controller) {
            $controller->registerRoutes();
        }
    }

    /**
     * Get aggregated state for SSR hydration.
     */
    public function getState(string $activeFormId): array
    {
        return $this->stateAggregator->aggregate(
            $this->registry->getAll(),
            $activeFormId
        );
    }

    /**
     * Access the form registry.
     */
    public function registry(): FormRegistry
    {
        return $this->registry;
    }
}
