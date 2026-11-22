<?php

/**
 * Gateway - user auth app
 * Version: 0.7.5
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
use Gateway\Enums\GatewayView;
use Gateway\Forms\LoginForm;
use Gateway\Forms\LostPasswordForm;
use Gateway\Forms\RegisterForm;
use Gateway\Forms\ResetPasswordForm;
use Shared\Policy\PasswordPolicy;
use Shared\Policy\UsernamePolicy;
use Shared\Build\AssetFile;

/**
 * Main Gateway singleton.
 * Mirrors LaunchpadCore architecture.
 */
final class GatewayCore
{
    private static ?self $instance = null;

    /**
     * Get singleton instance with optional DI for testing.
     */
    public static function instance(
        ?FormRegistry $registry = null,
        ?StateAggregator $aggregator = null,
        ?RedirectHandler $redirect = null
    ): self {
        return self::$instance ??= new self(
            $registry ?? new FormRegistry(),
            $aggregator ?? new StateAggregator(),
            $redirect ?? new RedirectHandler()
        );
    }

    /**
     * Reset singleton (for testing only).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    private function __construct(
        private readonly FormRegistry $registry,
        private readonly StateAggregator $stateAggregator,
        private readonly RedirectHandler $redirectHandler
    ) {
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

        // prevent WP sending registration emails to new users
        // we do it manually with sendActivationEmail()
        add_filter('wp_send_new_user_notification_to_user', '__return_false');

        // SEO — keep the auth page out of search results
        add_filter('rank_math/frontend/robots', [$this, 'filterRobots']);
        add_filter('wp_robots', [$this, 'filterCoreRobots']);
    }

    /**
     * True on the Gateway page in any of its states.
     */
    private function isGatewayPage(): bool
    {
        return is_page_template('templates/page-gateway.php');
    }

    /**
     * Rank Math robots directives for the auth page.
     *
     * A login screen has nothing to rank for, and every inbound link carries a
     * different ?redirect_to=, so crawling it only burns budget on duplicates.
     * rel="nofollow" on those links is a hint the crawler may ignore — noindex
     * on the page itself is the directive that settles it.
     *
     * @param array<string, string> $robots Directive map, e.g. ['index' => 'index'].
     * @return array<string, string>
     */
    public function filterRobots(array $robots): array
    {
        if (!$this->isGatewayPage()) {
            return $robots;
        }

        return ['index' => 'noindex', 'follow' => 'nofollow'];
    }

    /**
     * Same directive through core's wp_robots, for when Rank Math is inactive.
     *
     * @param array<string, bool|string> $robots
     * @return array<string, bool|string>
     */
    public function filterCoreRobots(array $robots): array
    {
        if (!$this->isGatewayPage()) {
            return $robots;
        }

        return wp_robots_no_robots($robots);
    }

    /**
     * Register built-in forms.
     */
    public function registerDefaultForms(FormRegistry $registry): void
    {
        $registry->register(GatewayView::LOGIN->value, new LoginForm(), 10);
        $registry->register(GatewayView::REGISTER->value, new RegisterForm(), 20);
        $registry->register(GatewayView::LOST_PASSWORD->value, new LostPasswordForm(), 30);
        $registry->register(GatewayView::RESET_PASSWORD->value, new ResetPasswordForm(), 40);
    }

    /**
     * Enqueue assets only on the Gateway page - the same test as the robots
     * filters, so it holds whatever the page's slug.
     * Mirrors LaunchpadCore::enqueueAssets()
     */
    public function enqueueAssets(): void
    {
        if (!$this->isGatewayPage()) {
            return;
        }

        $asset = AssetFile::read('assets/js/gateway-store.module.js', ['@wordpress/interactivity']);

        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/gateway',
                $asset['url'],
                $asset['dependencies'],
                $asset['version']
            );
            wp_enqueue_script_module('@starwishx/gateway');
        }

        // Infrastructure config — static per request, no routing knowledge needed.
        // wp_interactivity_state() merges on repeated calls: the template will add
        // application state (active form, form fields) on top of this in a second call.
        //
        // wp_add_inline_script('wp-interactivity', ...) is intentionally removed:
        // it targets the classic script pipeline and has no ordering guarantee
        // relative to @wordpress/interactivity module execution.
        wp_interactivity_state('gateway', [
            'gatewaySettings' => [
                'nonce'   => wp_create_nonce('wp_rest'),
                'restUrl' => rest_url('gateway/v1/'),
                'baseUrl' => GatewayView::LOGIN->url(),
            ],
            'passwordPolicy'    => PasswordPolicy::getClientRules(),
            'usernamePolicy'    => UsernamePolicy::getClientRules(),
            'validationStrings' => PasswordPolicy::getClientValidationStrings(),
        ]);
    }

    /**
     * Register REST API controllers.
     */
    public function registerRestRoutes(): void
    {
        foreach ([new AuthController(), new RegisterController(), new PasswordController()] as $controller) {
            $controller->registerRoutes();
        }
    }

    /**
     * Get aggregated state for SSR hydration.
     *
     * @return array<string, mixed>
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
