<?php

/**
 * Listing - Public Opportunities Discovery App
 * Architecture: Singleton with Dependency Injection
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * File: inc/listing/Core/ListingCore.php
 */

declare(strict_types=1);

namespace Listing\Core;

use Launchpad\Data\Repositories\CountriesRepository;
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
    private CountriesRepository $countriesRepository;

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
        ?TermCountingService $termCounter = null,
        ?CountriesRepository $countriesRepository = null
    ): self {
        if (!self::$instance) {
            $registry            = $registry            ?? new FilterRegistry();
            $queryBuilder        = $queryBuilder        ?? new QueryBuilder($registry);
            $termCounter         = $termCounter         ?? new TermCountingService($queryBuilder);
            $countriesRepository = $countriesRepository ?? new CountriesRepository();
            // Reuse service from the shared Favorites module
            $favoritesService    = \favorites()->service();
            $service             = $service             ?? new ListingService($queryBuilder, $termCounter, $favoritesService);
            $stateAggregator     = $stateAggregator     ?? new StateAggregator($service, $countriesRepository);

            self::$instance      = new self(
                $registry,
                $service,
                $stateAggregator,
                $grid ?? new ResultsGrid(),
                $termCounter,
                $countriesRepository
            );
        }

        return self::$instance;
    }

    private function __construct(
        FilterRegistry $registry,
        ListingService $service,
        StateAggregator $stateAggregator,
        ResultsGrid $grid,
        TermCountingService $termCounter,
        CountriesRepository $countriesRepository
    ) {
        $this->registry            = $registry;
        $this->service             = $service;
        $this->stateAggregator     = $stateAggregator;
        $this->grid                = $grid;
        $this->termCounter         = $termCounter;
        $this->countriesRepository = $countriesRepository;

        $this->bootstrap();
    }

    /**
     * Register all WordPress hooks.
     */
    private function bootstrap(): void
    {
        add_action('wp_enqueue_scripts',  [$this, 'enqueueAssets']);
        add_action('rest_api_init',       [$this, 'registerRestRoutes']);
        add_action('template_redirect',   [$this, 'redirectNumericCountryParams']);
        add_action('init', fn() => do_action('listing_register_filters', $this->registry), 20);
        add_action('listing_register_filters', [$this, 'registerDefaultFilters'], 5);
        // Invalidate the category term map transient whenever a term is saved.
        // 'saved_term' fires for both new and updated terms, after the DB write.
        add_action('saved_term', function (int $termId, int $ttId, string $taxonomy): void {
            if ($taxonomy === 'category-oportunities') {
                delete_transient('listing_category_term_map');
            }
        }, 10, 3);

        if (function_exists("rank_math_the_breadcrumbs")) {
            add_filter('rank_math/frontend/breadcrumb/items', [$this, 'add_opportunity_breadcrumb_base'], 10, 2);
        }

        // SEO-friendly category URLs: /opportunities/{slug}/
        if (defined('LISTING_PRETTY_CATEGORY_URLS') && LISTING_PRETTY_CATEGORY_URLS) {
            add_action('init', [$this, 'registerCategoryRewrites'], 5);
            add_filter('query_vars', fn(array $vars) => array_merge($vars, ['listing_cat']));
            add_filter('request', [$this, 'disambiguateCategoryUrl'], 20);
        }

        // SEO meta for the opportunity archive (title, description, OG, canonical)
        (new ListingSeoProvider())->register();
    }

    /**
     * Replace rank_math breadcrumbs URL base in case of non-existent archive for CPT
     */
    public function add_opportunity_breadcrumb_base($crumbs, $class)
    {
        if (is_singular('opportunity')) {
            $custom_base = [
                __('Opportunities', 'starwishx'),
                '/opportunities/',
                'hide_in_url' => false,
            ];
            array_splice($crumbs, 1, 0, [$custom_base]);
        }
        return $crumbs;
    }

    /**
     * Register rewrite rules for SEO-friendly category URLs.
     * Maps /opportunities/{slug}/ to the archive template with listing_cat query var.
     */
    public function registerCategoryRewrites(): void
    {
        // Negative lookahead excludes /page/ so default WP pagination still works
        add_rewrite_rule(
            'opportunities/(?!page/)([^/]+)/?$',
            'index.php?post_type=opportunity&listing_cat=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            'opportunities/(?!page/)([^/]+)/page/([0-9]+)/?$',
            'index.php?post_type=opportunity&listing_cat=$matches[1]&paged=$matches[2]',
            'top'
        );
    }

    /**
     * Disambiguate pretty category URLs from single post URLs.
     *
     * The rewrite rule for /opportunities/{slug}/ matches both category slugs
     * and post slugs because both occupy the same URL segment. When listing_cat
     * is set but doesn't match a valid taxonomy term, rewrite the query vars
     * so WordPress resolves the request as a single opportunity post instead.
     *
     * @param array $vars Parsed query variables from the matched rewrite rule.
     */
    public function disambiguateCategoryUrl(array $vars): array
    {
        if (empty($vars['listing_cat'])) {
            return $vars;
        }

        $term = get_term_by('slug', $vars['listing_cat'], 'category-oportunities');

        if ($term && !is_wp_error($term)) {
            return $vars;
        }

        // Not a category term — resolve as a single opportunity post.
        // post_type=opportunity is already set by the rewrite rule;
        // adding 'name' switches WordPress from archive to single-post mode.
        $vars['name'] = $vars['listing_cat'];
        unset($vars['listing_cat']);

        return $vars;
    }

    /**
     * 301 any numeric country query value to its alpha-2 slug, so that
     * `/opportunities/?country=804` becomes `/opportunities/?country=ua`
     * before the page renders.
     *
     * Why a redirect rather than just a canonical link tag:
     *   - A canonical is a hint; bots may take time to consolidate, and
     *     Bing/Yandex respect it less reliably than Google.
     *   - A 301 normalizes immediately for both bots and users, prevents
     *     ranking dilution and split crawl budget across `?country=804`
     *     and `?country=ua` for identical content, and silently catches
     *     accidental leaks (legacy links, raw-state shares, future bugs
     *     that emit ids by mistake).
     *
     * SPA in-page filter changes go through syncStateToUrl which already
     * emits slugs — so this only fires on full page loads.
     *
     * Uses QueryStringParser rather than $_GET so multi-value
     * (?country=pl&country=de — repeated-key, no brackets) round-trips
     * correctly: PHP's default $_GET would silently drop all but the
     * last value.
     */
    public function redirectNumericCountryParams(): void
    {
        if (!is_post_type_archive('opportunity')) {
            return;
        }

        $rawParams = QueryStringParser::fromServer();
        if (empty($rawParams['country'])) {
            return;
        }

        $rawCountries = (array) $rawParams['country'];

        // Bail if all values are already non-numeric (slug form) — no work to do.
        $hasNumeric = false;
        foreach ($rawCountries as $val) {
            if (is_numeric($val)) {
                $hasNumeric = true;
                break;
            }
        }
        if (!$hasNumeric) {
            return;
        }

        // Resolve each value to its alpha-2 code; numerics that don't
        // map to a known country are silently dropped (mirrors the
        // missing-slug behavior in StateAggregator::resolveCountryValues).
        $codes = [];
        foreach ($rawCountries as $val) {
            if (is_numeric($val)) {
                $row = $this->countriesRepository->getById((int) $val);
                if ($row) {
                    $codes[] = $row['code'];
                }
            } else {
                $codes[] = strtolower(sanitize_text_field((string) $val));
            }
        }

        // Rebuild the query string keeping the repeated-key shape that
        // syncStateToUrl emits (?key=v1&key=v2, no brackets), so the URL
        // layout stays consistent across server-side 301 and client-side
        // pushState navigation.
        $queryParts = [];
        foreach ($rawParams as $key => $val) {
            if ($key === 'country') {
                continue;
            }
            $vals = is_array($val) ? $val : [$val];
            foreach ($vals as $v) {
                $queryParts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $v);
            }
        }
        foreach (array_unique($codes) as $code) {
            $queryParts[] = 'country=' . rawurlencode((string) $code);
        }

        $path  = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $query = !empty($queryParts) ? '?' . implode('&', $queryParts) : '';

        wp_safe_redirect(home_url($path . $query), 301);
        exit;
    }

    /**
     * Register built-in sidebar filters.
     */
    public function registerDefaultFilters(FilterRegistry $registry): void
    {
        $categoryFilter = new CategoryFilter();
        $categoryFilter->setTermCounter($this->termCounter);
        $registry->register('category', $categoryFilter,              10);
        $registry->register('location', new LocationsFilterSimple(),  20);
        $registry->register('seekers',  new SeekersFilter(),          30);
        $registry->register('country',  new CountryFilter(),          40);
    }

    /**
     * Enqueue Interactivity API assets only on the Listing page.
     */
    public function enqueueAssets(): void
    {
        // Depends on where to place Listing App: page or archive
        // if (!is_page('listing')) {
        // if (!is_page('opportunities')) {
        if (!is_archive('opportunities')) {
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

        $listingConfig = [
            'nonce'   => wp_create_nonce('wp_rest'),
            'restUrl' => rest_url('listing/v1/'),
        ];

        if (defined('LISTING_PRETTY_CATEGORY_URLS') && LISTING_PRETTY_CATEGORY_URLS) {
            $listingConfig['prettyCategoryUrls'] = true;
            $listingConfig['archivePath'] = wp_parse_url(
                get_post_type_archive_link('opportunity'),
                PHP_URL_PATH
            ) ?: '/opportunities/';
        }

        wp_interactivity_state('listingSettings', [
            'config' => $listingConfig,
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
     * Called from archive-opportunity.php.
     *
     * QueryStringParser::fromServer() is used here rather than in StateAggregator
     * so the aggregator stays free of superglobal access and remains testable.
     *
     * @param array $filterOverrides Additional filters to merge (e.g. from pretty URL path).
     *                               These take precedence over query string values.
     */
    public function getState(array $filterOverrides = []): array
    {
        $rawFilters = QueryStringParser::fromServer();

        if ($filterOverrides) {
            $rawFilters = array_merge($rawFilters, $filterOverrides);
        }

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
