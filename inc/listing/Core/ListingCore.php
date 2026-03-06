<?php

/**
 * Listing - Public Opportunities Discovery App
 * Architecture: Singleton with Dependency Injection
 * Version: 0.6.1
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
use Favorites\Services\FavoritesService;
use Shared\Http\QueryStringParser;

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
    private TermCountingService $termCounter;

    /**
     * Reset singleton (for testing only).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Get singleton instance with optional DI for testing.
     *
     * Construction order matters here:
     *   1. FilterRegistry is created first (empty at this point).
     *   2. QueryBuilder receives the registry reference. Because PHP passes
     *      objects by reference, QueryBuilder::build() will see the fully
     *      populated registry by the time it is called — filters are registered
     *      later via the 'listing_register_filters' / 'init' action chain.
     *   3. Services and aggregators are wired with their dependencies.
     *   4. No setFilterRegistry() call is needed.
     */
    public static function instance(
        ?FilterRegistry $registry = null,
        ?ListingService $service = null,
        ?StateAggregator $stateAggregator = null,
        ?ResultsGrid $grid = null,
        ?QueryBuilder $queryBuilder = null,
        ?TermCountingService $termCounter = null
    ): self {
        if (!self::$instance) {
            $registry         = $registry        ?? new FilterRegistry();
            $queryBuilder     = $queryBuilder    ?? new QueryBuilder($registry);
            $termCounter      = $termCounter     ?? new TermCountingService($queryBuilder);
            // Reuse service from the shared Favorites module
            $favoritesService = \favorites()->service();
            $service          = $service         ?? new ListingService($queryBuilder, $termCounter, $favoritesService);
            $stateAggregator  = $stateAggregator ?? new StateAggregator($service);

            self::$instance   = new self(
                $registry,
                $service,
                $stateAggregator,
                $grid ?? new ResultsGrid(),
                $termCounter
            );
        }

        return self::$instance;
    }

    private function __construct(
        FilterRegistry $registry,
        ListingService $service,
        StateAggregator $stateAggregator,
        ResultsGrid $grid,
        TermCountingService $termCounter
    ) {
        $this->registry        = $registry;
        $this->service         = $service;
        $this->stateAggregator = $stateAggregator;
        $this->grid            = $grid;
        $this->termCounter     = $termCounter;

        $this->bootstrap();
    }

    /**
     * Register all WordPress hooks.
     */
    private function bootstrap(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('rest_api_init',      [$this, 'registerRestRoutes']);
        add_action('init', fn() => do_action('listing_register_filters', $this->registry), 20);
        add_action('listing_register_filters', [$this, 'registerDefaultFilters'], 5);
        // Invalidate the category term map transient whenever a term is saved.
        // 'saved_term' fires for both new and updated terms, after the DB write.
        add_action('saved_term', function (int $termId, int $ttId, string $taxonomy): void {
            if ($taxonomy === 'category-oportunities') {
                delete_transient('listing_category_term_map');
            }
        }, 10, 3);
    }

    /**
     * Register built-in sidebar filters.
     */
    public function registerDefaultFilters(FilterRegistry $registry): void
    {
        $categoryFilter = new CategoryFilter();
        $categoryFilter->setTermCounter($this->termCounter);

        $registry->register('category', $categoryFilter,              10);
        $registry->register('country',  new CountryFilter(),          20);
        $registry->register('location', new LocationsFilterSimple(),  30);
        $registry->register('seekers',  new SeekersFilter(),          40);
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

        // Favorites store is enqueued by the independent FavoritesCore module
        $listingDeps = ['@wordpress/interactivity'];
        if (is_user_logged_in()) {
            $listingDeps[] = '@starwishx/favorites';
        }

        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/listing',
                get_template_directory_uri() . '/assets/js/listing-store.module.js',
                array_merge($listingDeps, $asset['dependencies']),
                $asset['version']
            );
            wp_enqueue_script_module('@starwishx/listing');
        }

        wp_interactivity_state('listingSettings', [
            'config' => [
                'nonce'   => wp_create_nonce('wp_rest'),
                'restUrl' => rest_url('listing/v1/'),
            ],
        ]);

        wp_interactivity_state('listing', [
            'isUserLoggedIn' => is_user_logged_in(),
        ]);
    }

    /**
     * Register REST API controllers with injected services.
     */
    public function registerRestRoutes(): void
    {
        (new MainController($this->service))->registerRoutes();
    }

    /**
     * Get aggregated state for SSR hydration.
     * Called from page-listing-opportunities.php.
     *
     * QueryStringParser::fromServer() is used here rather than in StateAggregator
     * so the aggregator stays free of superglobal access and remains testable.
     */
    public function getState(): array
    {
        $rawFilters = QueryStringParser::fromServer();

        return $this->stateAggregator->aggregate($this->registry, $rawFilters);
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
