<?php
// File: inc/launchpad/Core/LaunchpadCore.php

declare(strict_types=1);

namespace Launchpad\Core;

// Import the new controllers
use Launchpad\Api\MainController;
use Launchpad\Api\ProfileController;
use Launchpad\Api\FavoritesController;
use Launchpad\Api\SecurityController;
use Launchpad\Api\OpportunitiesController;

final class LaunchpadCore
{
    private static ?self $instance = null;
    private PanelRegistry $registry;
    private StateAggregator $stateAggregator;
    private AccessController $accessController;

    public static function instance(
        ?PanelRegistry $registry = null,
        ?StateAggregator $aggregator = null,
        ?AccessController $access = null
    ): self {
        if (!self::$instance) {
            self::$instance = new self(
                $registry ?? new PanelRegistry(),
                $aggregator ?? new StateAggregator(),
                $access ?? new AccessController()
            );
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private function __construct(
        PanelRegistry $registry,
        StateAggregator $aggregator,
        AccessController $access
    ) {
        $this->registry = $registry;
        $this->stateAggregator = $aggregator;
        $this->accessController = $access;

        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        // Access control
        add_action('init', [$this->accessController, 'init']);

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // REST API - Using the new controller structure
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Panel registration hook
        add_action('init', fn() => do_action('launchpad_register_panels', $this->registry), 20);

        // Register default panels
        add_action('launchpad_register_panels', [$this, 'registerDefaultPanels'], 5);
    }

    public function registerDefaultPanels(PanelRegistry $registry): void
    {
        $registry->register('profile', new \Launchpad\Panels\ProfilePanel(), 10);
        $registry->register('favorites', new \Launchpad\Panels\FavoritesPanel(), 20);
        $registry->register('opportunities', new \Launchpad\Panels\OpportunitiesPanel(), 25);
        $registry->register('stats', new \Launchpad\Panels\StatsPanel(), 30);
        $registry->register('security', new \Launchpad\Panels\SecurityPanel(), 40);
    }

    public function enqueueAssets(): void
    {
        if (!is_page('launchpad')) {
            return;
        }

        // JS Module
        $asset_path = get_template_directory() . '/inc/launchpad/Assets/store.asset.php';
        $asset = file_exists($asset_path) ? include $asset_path : ['dependencies' => [], 'version' => '1.0.0'];

        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/launchpad',
                // get_template_directory_uri() . '/inc/launchpad/Assets/store.js',
                get_template_directory_uri() . '/assets/js/store.module.js',
                array_merge(['@wordpress/interactivity'], $asset['dependencies']),
                $asset['version']
            );
            wp_enqueue_script_module('@starwishx/launchpad');
        }
        // Settings for JS - Added loginUrl here
        wp_add_inline_script(
            'wp-interactivity',
            sprintf('window.launchpadSettings = %s;', wp_json_encode([
                'nonce'    => wp_create_nonce('wp_rest'),
                'restUrl'  => rest_url('launchpad/v1/'),
                'userId'   => get_current_user_id(),
                'loginUrl' => wp_login_url(home_url('/launchpad/')),
            ])),
            'before'
        );
    }

    /**
     * Bootstrap the API Controllers.
     * This replaces the single RestController instantiation.
     */
    public function registerRestRoutes(): void
    {
        // Define all API controllers in the system
        $controllers = [
            // Main Controller handles the generic /state endpoint and needs the registry
            new MainController($this->registry),

            // Domain specific controllers handle their own CRUD logic
            new ProfileController(),
            new FavoritesController(),
            new OpportunitiesController(),
            new SecurityController(),
        ];

        // Register routes for each controller
        foreach ($controllers as $controller) {
            $controller->registerRoutes();
        }
    }

    public function getState(int $userId, string $activePanelId): array
    {
        return $this->stateAggregator->aggregate(
            $this->registry->getAll(),
            $userId,
            $activePanelId
        );
    }

    public function registry(): PanelRegistry
    {
        return $this->registry;
    }
}
