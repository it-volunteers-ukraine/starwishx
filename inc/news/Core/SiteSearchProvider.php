<?php

/**
 * Site-wide search on WordPress's own search query.
 *
 * The old search ran on a WP page (slug `search`) with a ?search= parameter,
 * so WordPress never knew a search was happening: no is_search(), no native
 * pagination, no canonical. Switching the form to `s` puts the request on
 * core's search/%search%/ permastruct and search.php renders it.
 *
 * Lives in the news module because search results are news-card lists that
 * share the pagination and load-more contract with the news archives. If
 * search grows facets of its own it earns a module.
 *
 * File: inc/news/Core/SiteSearchProvider.php
 */

declare(strict_types=1);

namespace News\Core;

use WP_Query;

final class SiteSearchProvider
{
    public function register(): void
    {
        add_action('pre_get_posts',     [$this, 'applySearchQuery']);
        add_action('template_redirect', [$this, 'redirectLegacySearchUrls'], 2);
        add_action('template_redirect', [$this, 'redirectToPrettySearchUrl'], 3);
    }

    /**
     * Constrain the main search query to the post types users can find,
     * and apply the same per_page / sort contract as the news archives.
     */
    public function applySearchQuery(WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
            return;
        }

        $query->set('post_type',      sw_searchable_post_types());
        $query->set('post_status',    'publish');
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
     * 301 /?s=term onto /search/term/.
     *
     * The form submits a plain GET, so without this both URLs serve the same
     * results and compete for the same ranking. WordPress does not normalise
     * this itself.
     */
    public function redirectToPrettySearchUrl(): void
    {
        if (!is_search()) {
            return;
        }

        $term = get_search_query(false);

        if ($term === '') {
            return;
        }

        $pretty      = get_search_link($term);
        $pretty_path = (string) (wp_parse_url($pretty, PHP_URL_PATH) ?? '');

        // Plain permalinks: get_search_link() returns /?s=… and there is
        // nothing to normalise to.
        if ($pretty_path === '' || $pretty_path === '/') {
            return;
        }

        $current_path = (string) (wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');

        // Already on the pretty URL — including /search/term/page/2/
        if (strpos($current_path, $pretty_path) === 0) {
            return;
        }

        wp_safe_redirect($this->withDisplayParams($pretty), 301);
        exit;
    }

    /**
     * 301 the retired ?search= URLs onto native search.
     *
     * Old links, bookmarks and anything already indexed keep working.
     */
    public function redirectLegacySearchUrls(): void
    {
        if (!isset($_GET['search']) || is_search()) {
            return;
        }

        $term = sanitize_text_field(wp_unslash($_GET['search']));

        if ($term === '') {
            wp_safe_redirect(home_url('/'), 301);
            exit;
        }

        wp_safe_redirect($this->withDisplayParams(get_search_link($term)), 301);
        exit;
    }

    /**
     * Carry per_page / sortby / order across a redirect so a shared or
     * bookmarked URL keeps showing what it showed.
     */
    private function withDisplayParams(string $url): string
    {
        $carry = [];

        foreach (['per_page', 'sortby', 'order'] as $key) {
            if (isset($_GET[$key])) {
                $carry[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
            }
        }

        return $carry ? add_query_arg($carry, $url) : $url;
    }
}
