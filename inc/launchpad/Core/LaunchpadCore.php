<?php

/**
 * Launchpad user admin panel app
 * Version: 0.5.1
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
use Launchpad\Services\MediaService;
use Launchpad\Data\Repositories\FavoritesRepository;

final class LaunchpadCore
{
    // We store the services here so they live as long as the Core lives
    /** @var array<string, object> Shared service instances */
    private array $services = [];

    // Store the repository instance here to avoid excessed instantiation
    private FavoritesRepository $favoritesRepo;

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

        // Initialize services immediately
        $this->initServices();

        // Start WordPress hooks
        $this->bootstrap();
    }

    /**
     * Create the shared service instances.
     */
    private function initServices(): void
    {
        // $this->services['favorites']     = new FavoritesService();
        // Initialize Repository ONCE
        $this->favoritesRepo             = new FavoritesRepository();
        // Inject the SAME repository instance into the Service (Optional, but cleaner if Service supports it)
        // For now, we keep the service creation as is, assuming it creates its own or accepts one.
        // If FavoritesService constructor accepts ($repo), you should pass $this->favoritesRepo there too.
        $this->services['favorites']     = new FavoritesService($this->favoritesRepo);

        $this->services['profile']       = new ProfileService();
        $this->services['opportunities'] = new OpportunitiesService();
        $this->services['security']      = new SecurityService();
        // Stats depends on Favorites - we pass the shared instance here
        $this->services['stats']         = new StatsService($this->services['favorites']);
        $this->services['comments']      = new CommentsService();
        $this->services['media']         = new MediaService();
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

        // Data cleanup hooks
        add_action('delete_post', [$this, 'cleanupPostFavorites']);
        add_action('delete_user', [$this, 'cleanupUserFavorites']);

        // Cron job for orphan cleanup
        add_action('launchpad_daily_cleanup', [$this->services['media'], 'cleanupOrphans']);
        if (!wp_next_scheduled('launchpad_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'launchpad_daily_cleanup');
        }
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
            new \Launchpad\Api\MediaController($this->services['media']),
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
        $userId = get_current_user_id();

        // Always enqueue Favorites Store if user is logged in
        // both for frontend and backend as we separate it
        // if (is_user_logged_in()) {
        //     $this->enqueueFavoritesStore($userId);
        // }

        // Main Dashboard App
        if (is_page('launchpad') && is_user_logged_in()) {

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

            $this->enqueueFavoritesStore($userId);
        }

        // Single Opportunity post Interactive Comments App
        if (is_singular('opportunity') && is_user_logged_in()) {
            $this->enqueueCommentsAssets();
            $this->enqueueFrontendStore($userId);
        }
    }

    private function enqueueCommentsAssets(): void
    {
        $asset_path = get_template_directory() . '/inc/launchpad/Assets/store.asset.php';
        $asset = file_exists($asset_path) ? include $asset_path : ['dependencies' => [], 'version' => '1.0.0'];

        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/launchpad-comments',
                // get_template_directory_uri() . '/inc/launchpad/Assets/comments-store.js',
                get_template_directory_uri() . '/assets/js/comments-store.module.js',
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

    /**
     * Enqueue the standalone Favorites Domain Store
     * Used on both frontend and backend
     */
    private function enqueueFavoritesStore(int $userId): void
    {
        $asset_path = get_template_directory() . '/inc/launchpad/Assets/store.asset.php';
        $asset = file_exists($asset_path) ? include $asset_path : ['dependencies' => [], 'version' => '1.0.0'];

        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/launchpad-favorites',
                // get_template_directory_uri() . '/inc/launchpad/Assets/favorites-store.js',
                get_template_directory_uri() . '/assets/js/favorites-store.module.js',
                array_merge(['@wordpress/interactivity'], $asset['dependencies']),
                $asset['version']
            );
            wp_enqueue_script_module('@starwishx/launchpad-favorites');
        }

        // Hydrate state for launchpad/favorites namespace
        // REUSE the instance:
        $ids = $this->favoritesRepo->getFavoriteIds($userId, 'post', 9999, 0);

        // Inject 'config' directly into the state
        wp_interactivity_state('launchpad/favorites', [
            'myFavoriteIds' => $ids,
            'config'        => [
                'nonce'   => wp_create_nonce('wp_rest'),
                'restUrl' => rest_url('launchpad/v1/'),
            ]
        ]);

        // Output global settings reliably using footer action
        add_action('wp_footer', function () {
            echo '<script id="launchpad-global-settings">';
            echo 'window.launchpadGlobal = ' . wp_json_encode([
                'nonce'   => wp_create_nonce('wp_rest'),
                'restUrl' => rest_url('launchpad/v1/')
            ]) . ';';
            echo '</script>';
        });
    }

    /**
     * Enqueue Isolated Frontend Store
     */
    private function enqueueFrontendStore(int $userId): void
    {
        // 1. Register Script
        $asset_path = get_template_directory() . '/inc/launchpad/Assets/store.asset.php';
        $asset = file_exists($asset_path) ? include $asset_path : ['dependencies' => [], 'version' => '1.0.0'];

        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/frontend-opportunities',
                get_template_directory_uri() . '/inc/launchpad/Assets/single-opportunity-store.js',
                array_merge(['@wordpress/interactivity'], $asset['dependencies']),
                $asset['version']
            );
            wp_enqueue_script_module('@starwishx/frontend-opportunities');
        }

        // 2. Prepare Data (Map Structure)
        $statusMap = [];
        $repo = $this->favoritesRepo; // Reuse instance

        if (is_singular('opportunity')) {
            // SINGLE PAGE: 1 DB Query, 1 Result.
            $post_id = get_the_ID();
            $is_fav = $repo->isFavorite($userId, $post_id);
            $statusMap[$post_id] = $is_fav;
        }
        /* 
        // Future Archive Logic:
        elseif (is_post_type_archive('opportunity')) {
             // Logic to fetch IDs for current loop query only
        } 
        */

        // 3. Hydrate
        wp_interactivity_state('starwishx/opportunities', [
            'statusMap' => $statusMap, // { 624: true }
            'config'    => [
                'nonce'   => wp_create_nonce('wp_rest'),
                'restUrl' => rest_url('launchpad/v1/')
            ]
        ]);
    }

    public function cleanupPostFavorites(int $postId): void
    {
        // REUSE the instance:
        $this->favoritesRepo->deleteByPost($postId);
    }

    public function cleanupUserFavorites(int $userId): void
    {
        $this->favoritesRepo->deleteByUser($userId);
    }
}
