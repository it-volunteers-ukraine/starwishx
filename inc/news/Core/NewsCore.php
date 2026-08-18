<?php

/**
 * News module — routing and archive queries.
 *
 * Owns the SEO-friendly category URL /news/{category}/, which replaces the
 * old page-hierarchy URL /news/news-by-category/{category}/.
 *
 * File: inc/news/Core/NewsCore.php
 */

declare(strict_types=1);

namespace News\Core;

use News\Api\ArchiveController;
use WP_Query;

final class NewsCore
{
    public const POST_TYPE = 'news';
    public const TAXONOMY  = 'category-oportunities';
    public const QUERY_VAR = 'news_cat';

    /** Old URL base, kept alive only to 301 away from it. */
    private const LEGACY_BASE = 'news/news-by-category';

    /** Bump to trigger a one-shot rewrite flush after deploy. */
    private const REWRITE_VERSION = '1';

    private static ?self $instance = null;

    public static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Reset singleton (for testing only). */
    public static function reset(): void
    {
        self::$instance = null;
    }

    private function __construct()
    {
        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        add_action('init',              [$this, 'registerCategoryRewrites'], 5);
        add_action('init',              [$this, 'maybeFlushRewrites'], 20);
        add_filter('query_vars',        [$this, 'registerQueryVars']);
        add_filter('request',           [$this, 'disambiguateCategoryUrl'], 20);
        add_action('pre_get_posts',     [$this, 'applyCategoryArchiveQuery']);
        add_filter('archive_template',  [$this, 'useCategoryArchiveTemplate']);
        // Priority 1: must beat the 404 template for retired URLs
        add_action('template_redirect', [$this, 'redirectLegacyCategoryUrls'], 1);

        add_action('rest_api_init',      [$this, 'registerRestRoutes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // Canonical / title / rel prev-next for the category archive
        (new NewsSeoProvider())->register();

        // Site-wide search on WordPress's own search query
        (new SiteSearchProvider())->register();
    }

    public function registerRestRoutes(): void
    {
        (new ArchiveController())->registerRoutes();
    }

    /**
     * Load-more module — only on pages that can paginate a card list.
     */
    public function enqueueAssets(): void
    {
        if (!$this->isPaginatedContext() || !function_exists('wp_register_script_module')) {
            return;
        }

        wp_register_script_module(
            '@starwishx/news',
            get_template_directory_uri() . '/assets/js/news-store.module.js',
            ['@wordpress/interactivity']
        );
        wp_enqueue_script_module('@starwishx/news');

        $this->hydrateState();
    }

    /**
     * Infrastructure state for the load-more store.
     *
     * PHP stays the single i18n authority: every string the store can render
     * is hydrated here rather than hardcoded in JS.
     */
    private function hydrateState(): void
    {
        wp_interactivity_state('news', [
            'restUrl' => rest_url('news/v1/posts'),
            'source'  => $this->currentSource(),
            'i18n'    => [
                'loadMore' => __('Show more', 'starwishx'),
                'loading'  => __('Loading…', 'starwishx'),
                'error'    => __('Could not load more items. Please try again.', 'starwishx'),
            ],
        ]);
    }

    /**
     * True where a paginated card list is rendered.
     *
     * /news/ is excluded on purpose: archive-news.php is a curated
     * per-category layout, not a paginated list, so there is nothing there
     * to load more of.
     */
    public function isPaginatedContext(): bool
    {
        return is_search() || (string) get_query_var(self::QUERY_VAR) !== '';
    }

    /**
     * Source key the REST route expects for the current page.
     */
    public function currentSource(): string
    {
        return is_search() ? 'search' : 'news_category';
    }

    /**
     * Canonical URL of a category archive.
     *
     * Derived from the post type archive link so a future change of the
     * `news` rewrite slug propagates here for free.
     */
    public function categoryUrl(string $slug): string
    {
        $base = get_post_type_archive_link(self::POST_TYPE);

        if (!$base) {
            $base = home_url('/' . self::POST_TYPE . '/');
        }

        return user_trailingslashit(trailingslashit($base) . $slug);
    }

    /**
     * Rewrite rules for /news/{category}/.
     *
     * The negative lookahead keeps WordPress's own /news/page/N/ archive
     * pagination working — otherwise `page` would be read as a category.
     */
    public function registerCategoryRewrites(): void
    {
        add_rewrite_rule(
            self::POST_TYPE . '/(?!page/)([^/]+)/?$',
            'index.php?post_type=' . self::POST_TYPE . '&' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            self::POST_TYPE . '/(?!page/)([^/]+)/page/([0-9]+)/?$',
            'index.php?post_type=' . self::POST_TYPE . '&' . self::QUERY_VAR . '=$matches[1]&paged=$matches[2]',
            'top'
        );
    }

    /**
     * Flush rewrites once per REWRITE_VERSION.
     *
     * Without this the new URLs 404 until someone opens Settings →
     * Permalinks, which is a poor first-run experience for contributors.
     */
    public function maybeFlushRewrites(): void
    {
        if (get_option('sw_news_rewrites_version') === self::REWRITE_VERSION) {
            return;
        }

        flush_rewrite_rules(false);
        update_option('sw_news_rewrites_version', self::REWRITE_VERSION, false);
    }

    public function registerQueryVars(array $vars): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    /**
     * Tell category archives apart from single posts.
     *
     * /news/{segment}/ matches both a category slug and a post slug — the
     * `news` CPT publishes its singles under the same base. When the segment
     * is not a real term, hand the request back to WordPress as a single post.
     */
    public function disambiguateCategoryUrl(array $vars): array
    {
        if (empty($vars[self::QUERY_VAR])) {
            return $vars;
        }

        $term = get_term_by('slug', $vars[self::QUERY_VAR], self::TAXONOMY);

        if ($term && !is_wp_error($term)) {
            return $vars;
        }

        // Not a term — resolve as a single news post instead.
        // post_type=news is already set by the rewrite rule; adding `name`
        // switches WordPress from archive to single-post mode.
        $vars['name'] = $vars[self::QUERY_VAR];
        unset($vars[self::QUERY_VAR]);

        return $vars;
    }

    /**
     * Constrain the main query on /news/{category}/.
     *
     * news_cat is a custom query var, so the taxonomy filter has to be
     * applied here — and applying it to the main query is what gives the
     * template free, correct pagination via max_num_pages.
     */
    public function applyCategoryArchiveQuery(WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        $slug = (string) $query->get(self::QUERY_VAR);

        if ($slug === '') {
            return;
        }

        $query->set('post_type',   self::POST_TYPE);
        $query->set('post_status', 'publish');
        $query->set('tax_query', [[
            'taxonomy' => self::TAXONOMY,
            'field'    => 'slug',
            'terms'    => $slug,
        ]]);
        $query->set('posts_per_page', sw_get_per_page());

        $sort = sw_get_sort_params();

        if ($sort['orderby'] !== null) {
            $query->set('orderby', $sort['orderby']);
        }

        if ($sort['order'] !== null) {
            $query->set('order', $sort['order']);
        }
    }

    /**
     * /news/ keeps archive-news.php; /news/{category}/ gets its own template.
     */
    public function useCategoryArchiveTemplate(string $template): string
    {
        if ((string) get_query_var(self::QUERY_VAR) === '') {
            return $template;
        }

        $alt = locate_template('templates/archive-news-category.php');

        return $alt ?: $template;
    }

    /**
     * 301 the retired /news/news-by-category/… URLs.
     *
     * Matched on the request path rather than a query var: the rewrite rule
     * that used to produce those URLs is gone, so nothing parses them any more.
     */
    public function redirectLegacyCategoryUrls(): void
    {
        $path = trim((string) (wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''), '/');

        if ($path !== self::LEGACY_BASE && strpos($path, self::LEGACY_BASE . '/') !== 0) {
            return;
        }

        $segments = explode('/', $path);
        $slug     = isset($segments[2]) ? sanitize_title($segments[2]) : '';

        $target = $slug !== ''
            ? $this->categoryUrl($slug)
            : (get_post_type_archive_link(self::POST_TYPE) ?: home_url('/'));

        wp_safe_redirect($target, 301);
        exit;
    }
}
