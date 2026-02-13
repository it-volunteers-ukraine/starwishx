<?php

/**
 * Listing - Public Opportunities Discovery App
 * Architecture: Singleton with Dependency Injection
 * Version: 0.5.1
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 * 
 * File: inc/listing/Core/ListingCore.php
 */

declare(strict_types=1);

namespace Listing\Core;

use Listing\Api\MainController;
use Listing\Services\ListingService;
use Listing\Filters\CategoryFilter;
use Listing\Filters\CountryFilter;
use Listing\Filters\LocationsFilterSimple;
use Listing\Filters\SeekersFilter;
use Listing\Services\TermCountingService;
use Launchpad\Data\Repositories\FavoritesRepository;

/**
 * Main Listing singleton.
 * Orchestrates the public-facing discovery engine.
 */
final class ListingCore
{
    private static ?self $instance = null;

    private FilterRegistry $registry;
    private ListingService $service;
    private StateAggregator $stateAggregator;
    private ResultsGrid $grid;
    private QueryBuilder $queryBuilder;
    private TermCountingService $termCounter;
    private FavoritesRepository $favoritesRepo;

    /**
     * Reset singleton (for testing only).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
    /**
     * Get singleton instance with optional DI for testing.
     */
    public static function instance(
        ?FilterRegistry $registry = null,
        ?ListingService $service = null,
        ?StateAggregator $stateAggregator = null,
        ?ResultsGrid $grid = null,
        ?QueryBuilder $queryBuilder = null,
        ?TermCountingService $termCounter = null,
        ?FavoritesRepository $favoritesRepo = null
    ): self {
        if (!self::$instance) {
            // All dependencies created externally and injected
            // Ensure DI chain is respected
            $queryBuilder = $queryBuilder ?? new QueryBuilder();
            $termCounter = $termCounter ?? new TermCountingService($queryBuilder);
            $registry = $registry ?? new FilterRegistry();
            $queryBuilder->setFilterRegistry($registry);
            $service = $service ?? new ListingService($queryBuilder, $termCounter);
            $stateAggregator = $stateAggregator ?? new StateAggregator($service, $queryBuilder);
            $favoritesRepo = $favoritesRepo ?? new FavoritesRepository();

            self::$instance = new self(
                $registry,
                $service,
                $stateAggregator,
                $grid ?? new ResultsGrid(),
                $queryBuilder,
                $termCounter,
                $favoritesRepo
            );
        }
        return self::$instance;
    }

    private function __construct(
        FilterRegistry $registry,
        ListingService $service,
        StateAggregator $stateAggregator,
        ResultsGrid $grid,
        QueryBuilder $queryBuilder,
        TermCountingService $termCounter,
        FavoritesRepository $favoritesRepo
    ) {
        $this->registry = $registry;
        $this->service = $service;
        $this->stateAggregator = $stateAggregator;
        $this->grid = $grid;
        $this->queryBuilder = $queryBuilder;
        $this->termCounter = $termCounter;
        $this->favoritesRepo = $favoritesRepo;

        $this->bootstrap();
    }

    /**
     * Register all WordPress hooks.
     */
    private function bootstrap(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('init', fn() => do_action('listing_register_filters', $this->registry), 20);
        add_action('listing_register_filters', [$this, 'registerDefaultFilters'], 5);
    }

    /**
     * Register built-in sidebar filters.
     */
    public function registerDefaultFilters(FilterRegistry $registry): void
    {
        $categoryFilter = new CategoryFilter();
        $categoryFilter->setTermCounter($this->termCounter);
        $registry->register('category', $categoryFilter, 10);
        $registry->register('country',  new CountryFilter(), 20);
        $registry->register('location', new LocationsFilterSimple(), 30);
        $registry->register('seekers',  new SeekersFilter(), 40);
    }

    /**
     * Enqueue Interactivity API assets only on the Listing page.
     */
    public function enqueueAssets(): void
    {
        if (!is_page('listing')) {
            return;
        }
        
        $asset_path = get_template_directory() . '/inc/listing/Assets/listing-store.asset.php';
        $asset = file_exists($asset_path)
            ? include $asset_path
            : ['dependencies' => [], 'version' => '1.0.0'];

        if (is_user_logged_in()) {
            $userId = get_current_user_id();

            wp_enqueue_script_module(
                'launchpad-favorites',
                get_template_directory_uri() . '/assets/js/favorites-store.module.js',
                ['@wordpress/interactivity']
            );

            $ids = $this->favoritesRepo->getFavoriteIds($userId, 'post', 9999, 0);
            wp_interactivity_state('launchpad/favorites', [
                'myFavoriteIds' => $ids,
                'config'        => [
                    'nonce'   => wp_create_nonce('wp_rest'),
                    'restUrl' => rest_url('launchpad/v1/'),
                ]
            ]);

            if (function_exists('wp_register_script_module')) {
                wp_register_script_module(
                    '@starwishx/listing',
                    // get_template_directory_uri() . '/inc/listing/Assets/listing-store.js',
                    get_template_directory_uri() . '/assets/js/listing-store.module.js',
                    array_merge([
                        '@wordpress/interactivity',
                        'launchpad-favorites'
                    ], $asset['dependencies']),
                    $asset['version']
                );
                wp_enqueue_script_module('@starwishx/listing');
            }
        } else {
            if (function_exists('wp_register_script_module')) {
                wp_register_script_module(
                    '@starwishx/listing',
                    // get_template_directory_uri() . '/inc/listing/Assets/listing-store.js',
                    get_template_directory_uri() . '/assets/js/listing-store.module.js',
                    array_merge([
                        '@wordpress/interactivity',
                        // 'launchpad-favorites'
                    ], $asset['dependencies']),
                    $asset['version']
                );
                wp_enqueue_script_module('@starwishx/listing');
            }
        }

        wp_interactivity_state('listingSettings', [
            'config' => [
                'nonce'   => wp_create_nonce('wp_rest'),
                'restUrl' => rest_url('listing/v1/'),
            ]
        ]);
    }

    /**
     * Register REST API controllers with injected services.
     */
    public function registerRestRoutes(): void
    {
        // Pass the already instantiated service to the controller
        // (new MainController($this->service))->registerRoutes();
        $controller = new MainController($this->service);
        $controller->registerRoutes();
    }

    /**
     * Get aggregated state for SSR hydration.
     * Called from page-listing-opportunities.php
     */
    public function getState(): array
    {
        return $this->stateAggregator->aggregate($this->registry);
    }

    /**
     * Render the grid component.
     */
    public function renderGrid(): string
    {
        return $this->grid->render();
    }

    /**
     * Access the filter registry.
     */
    public function registry(): FilterRegistry
    {
        return $this->registry;
    }

    /**
     * Access the search service.
     */
    public function service(): ListingService
    {
        return $this->service;
    }
}
