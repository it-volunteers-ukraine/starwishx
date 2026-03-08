<?php

/**
 * Launchpad user admin panel app
 * Version: 0.6.1
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * 
 * License: GPL v2 or later
 * 
 */

// File: inc/launchpad/Core/LaunchpadCore.php

declare(strict_types=1);

namespace Launchpad\Core;

use Launchpad\Services\OpportunitiesService;
use Launchpad\Services\ProfileService;
use Launchpad\Services\StatsService;
use Launchpad\Services\SecurityService;
use Launchpad\Services\CommentsService;
use Launchpad\Services\MediaService;
use Shared\Policy\PasswordPolicy;

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
        // Favorites is now an independent module — get the shared instance
        $this->services['favorites']     = \favorites()->service();

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
        $registry->register('opportunities', new \Launchpad\Panels\OpportunitiesPanel($this->services['opportunities'], $this->services['profile']), 10);
        $registry->register('profile',       new \Launchpad\Panels\ProfilePanel($this->services['profile']), 30);
        $registry->register('favorites',     new \Launchpad\Panels\FavoritesPanel($this->services['favorites']), 20);
        // $registry->register('stats',         new \Launchpad\Panels\StatsPanel($this->services['stats']), 40);
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
            // FavoritesController is now registered by the independent Favorites module
            new \Launchpad\Api\OpportunitiesController($this->services['opportunities'], $this->services['profile']),
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

            // Infrastructure config — static per request, no routing knowledge needed.
            // wp_interactivity_state() merges on repeated calls: the template will add
            // application state (panels, active panel) on top of this in a second call.
            wp_interactivity_state('launchpad', [
                'launchpadSettings' => [
                    'nonce'               => wp_create_nonce('wp_rest'),
                    'restUrl'             => rest_url('launchpad/v1/'),
                    'userId'              => get_current_user_id(),
                    'loginUrl'            => wp_login_url(home_url('/launchpad/')),
                    'generatePasswordUrl' => rest_url('gateway/v1/password/generate'),
                    'passwordPolicy'      => PasswordPolicy::getClientRules(),
                ],
            ]);
        }

        // Single Opportunity post Interactive Comments App
        if ((is_singular('opportunity') || is_singular('project')) && is_user_logged_in()) {
            $this->enqueueCommentsAssets();
            $this->enqueueFrontendStore($userId);
        } elseif (is_singular('opportunity') || is_singular('project')) {
            // Guest: load store without favorites data so the popup guard works
            $this->enqueueFrontendStore(0);
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

        // Infrastructure config — static per request, no post-specific knowledge needed.
        // wp_interactivity_state() merges: the template part (comments-interactive.php)
        // adds application state (comments list, aggregates, form fields) on top.
        wp_interactivity_state('launchpadComments', [
            'settings' => [
                'nonce'    => wp_create_nonce('wp_rest'),
                'restUrl'  => rest_url('launchpad/v1/'),
                'messages' => [
                    'reviewPosted'  => __('Review posted successfully!', 'starwishx'),
                    'updateSaved'   => __('Update saved.', 'starwishx'),
                    'submitError'   => __('An error occurred while posting.', 'starwishx'),
                ],
            ],
        ]);
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
                get_template_directory_uri() . '/assets/js/single-opportunity-store.module.js',
                array_merge(['@wordpress/interactivity', '@starwishx/favorites'], $asset['dependencies']),
                $asset['version']
            );
            wp_enqueue_script_module('@starwishx/frontend-opportunities');
        }

        // 2. Hydrate (favorites state is now handled by FavoritesCore)
        $postId = (int) get_the_ID();
        wp_interactivity_state('starwishx/opportunities', [
            'isUserLoggedIn' => $userId > 0,
            'canFavorite'    => get_post_status() === 'publish',
            'isFavorite'     => $userId > 0 && function_exists('favorites') && \favorites()->isUserFavorite($postId),
        ]);
    }
}
