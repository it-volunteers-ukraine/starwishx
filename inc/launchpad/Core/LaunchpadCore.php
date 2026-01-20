<?php

/**
 * Launchpad user admin panel app
 * Version: 0.3.1
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * 
 * License: GPL v2 or later
 * 
 */

// File: inc/launchpad/Core/LaunchpadCore.php

declare(strict_types=1);

namespace Launchpad\Core;

use Launchpad\Services\FavoritesService;
use Launchpad\Services\OpportunitiesService;
use Launchpad\Services\ProfileService;
use Launchpad\Services\StatsService;
use Launchpad\Services\SecurityService;
use Launchpad\Services\CommentsService;

final class LaunchpadCore
{
    // We store the services here so they live as long as the Core lives
    /** @var array<string, object> Shared service instances */
    private array $services = [];

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

        // 1. Initialize services immediately so they are ready for anything
        $this->initServices();

        // 2. Start WordPress hooks
        $this->bootstrap();
    }

    /**
     * Create the shared service instances.
     */
    private function initServices(): void
    {
        $this->services['favorites']     = new FavoritesService();
        $this->services['profile']       = new ProfileService();
        $this->services['opportunities'] = new OpportunitiesService();
        $this->services['security']      = new SecurityService();
        // Stats depends on Favorites - we pass the shared instance here
        $this->services['stats']         = new StatsService($this->services['favorites']);
        $this->services['comments']      = new CommentsService();
    }

    private function bootstrap(): void
    {

        // Access control
        add_action('init', [$this->accessController, 'init']);

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // REST API - Using the new controller structure
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Register the filter to intercept Gateway redirects
        add_filter('gateway_auth_redirect_url', [$this, 'handleGatewayRedirect'], 10, 2);

        // Panel registration hook
        add_action('init', fn() => do_action('launchpad_register_panels', $this->registry), 20);

        // Register default panels
        add_action('launchpad_register_panels', [$this, 'registerDefaultPanels'], 5);
    }

    /**
     * Register default panels using SHARED services from the $services array.
     */
    public function registerDefaultPanels(PanelRegistry $registry): void
    {
        // Register Panels with injected Services
        $registry->register('opportunities', new \Launchpad\Panels\OpportunitiesPanel($this->services['opportunities']), 10);
        $registry->register('profile',       new \Launchpad\Panels\ProfilePanel($this->services['profile']), 30);
        // Security might not need a service yet
        // $registry->register('security',      new \Launchpad\Panels\SecurityPanel(), 40);
        // $registry->register('stats',         new \Launchpad\Panels\StatsPanel($this->services['stats']), 40);
        $registry->register('favorites',     new \Launchpad\Panels\FavoritesPanel($this->services['favorites']), 20);
    }

    /**
     * Bootstrap the API Controllers.
     * Register REST API controllers using SHARED services
     */
    public function registerRestRoutes(): void
    {
        // Define all API controllers in the system
        $controllers = [
            // Main Controller handles the generic /state endpoint and needs the registry
            new \Launchpad\Api\MainController($this->registry),
            // Domain specific controllers handle their own CRUD logic
            new \Launchpad\Api\ProfileController($this->services['profile']),
            new \Launchpad\Api\FavoritesController($this->services['favorites']),
            new \Launchpad\Api\OpportunitiesController($this->services['opportunities']),
            // new \Launchpad\Api\SecurityController(),
            new \Launchpad\Api\SecurityController($this->services['security']),
            new \Launchpad\Api\CommentsController($this->services['comments']),
        ];

        // Register routes for each controller
        foreach ($controllers as $controller) {
            $controller->registerRoutes();
        }
    }

    /**
     * Intercepts the Gateway redirect and sends users to Launchpad if eligible.
     */
    public function handleGatewayRedirect(string $url, \WP_User $user): string
    {
        // Use the AccessController we already have to decide
        if ($this->accessController->shouldUseLaunchpad($user->ID)) {
            return home_url('/launchpad/');
        }

        return $url;
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

    public function accessController(): AccessController
    {
        return $this->accessController;
    }

    public function enqueueAssets(): void
    {
        // Main Dashboard App
        if (is_page('launchpad')) {

            // JS Module
            $asset_path = get_template_directory() . '/inc/launchpad/Assets/store.asset.php';
            $asset = file_exists($asset_path) ? include $asset_path : ['dependencies' => [], 'version' => '1.0.0'];

            if (function_exists('wp_register_script_module')) {
                wp_register_script_module(
                    '@starwishx/launchpad',
                    // get_template_directory_uri() . '/inc/launchpad/Assets/launchpad-store.js', //for debugging
                    get_template_directory_uri() . '/assets/js/launchpad-store.module.js',
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

            wp_enqueue_style('dashicons');
        }

        // 2. Single Opportunity Comments App (New)
        if (is_singular('opportunity')) {
            $this->enqueueCommentsAssets();
        }
    }

    private function enqueueCommentsAssets(): void
    {
        $asset_path = get_template_directory() . '/inc/launchpad/Assets/store.asset.php';
        $asset = file_exists($asset_path) ? include $asset_path : ['dependencies' => [], 'version' => '1.0.0'];

        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/launchpad-comments',
                get_template_directory_uri() . '/inc/launchpad/Assets/comments-store.js',
                array_merge(['@wordpress/interactivity'], $asset['dependencies']),
                $asset['version']
            );
            wp_enqueue_script_module('@starwishx/launchpad-comments');
        }

        // Inject lightweight settings specifically for comments
        // wp_add_inline_script(
        //     'wp-interactivity',
        //     sprintf('window.launchpadCommentsSettings = %s;', wp_json_encode([
        //         'nonce'   => wp_create_nonce('wp_rest'),
        //         'restUrl' => rest_url('launchpad/v1/'),
        //     ])),
        //     'before'
        // );
    }

    /**
     * Public accessor for Services (Service Locator pattern)
     * Used by templates/partials where DI is not possible.
     */
    public function getService(string $id): ?object
    {
        return $this->services[$id] ?? null;
    }
}
