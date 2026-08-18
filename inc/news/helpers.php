<?php

/**
 * News module — global helpers.
 *
 * These are the survivors of inc/helpers.php, which was deleted with the
 * legacy blocks: every my_* function in it (my_query_args_prepare,
 * my_query_search, my_post_type, my_category_colors, pagination_url,
 * seach_params_from_blocks, …) existed only to serve the ACF pagination,
 * search-page and news-page-category blocks.
 *
 * What is left is the display contract shared by the news archives and
 * search: how many items a page shows and how they are sorted. Both the
 * templates and the REST route read it from here, so a page of results and
 * its pagination can never disagree again.
 *
 * File: inc/news/helpers.php
 */

if (! function_exists('sw_news')) {
    /**
     * News module singleton.
     *
     * Prefixed `sw_news()` rather than the bare `news()` used by the other
     * modules: `news` is a name a plugin is quite likely to claim.
     */
    function sw_news(): \News\Core\NewsCore
    {
        return \News\Core\NewsCore::instance();
    }
}

if (! function_exists('sw_searchable_post_types')) {
    /**
     * Post types users can find through site search.
     */
    function sw_searchable_post_types(): array
    {
        return apply_filters('sw_searchable_post_types', ['news', 'opportunity', 'project']);
    }
}

if (! function_exists('sw_get_allowed_per_page')) {
    /**
     * Allowed posts_per_page values — the single source of truth.
     */
    function sw_get_allowed_per_page(): array
    {
        $allowed = apply_filters('sw_allowed_per_page', [4, 8, 12]);
        $allowed = array_values(array_unique(array_filter(array_map('intval', (array) $allowed))));

        return $allowed ?: [12];
    }
}

if (! function_exists('sw_get_per_page')) {
    /**
     * per_page from the request, clamped to the allowed list.
     */
    function sw_get_per_page(): int
    {
        $allowed = sw_get_allowed_per_page();
        $default = in_array(12, $allowed, true) ? 12 : (int) end($allowed);

        if (! isset($_GET['per_page'])) {
            return $default;
        }

        $per_page = (int) sanitize_text_field(wp_unslash($_GET['per_page']));

        return in_array($per_page, $allowed, true) ? $per_page : $default;
    }
}

if (! function_exists('sw_get_sort_params')) {
    /**
     * sortby/order from the request, clamped to a whitelist.
     *
     * Returns WP_Query's key names. The code this replaced set an `sortby`
     * argument, which WP_Query ignores — sorting had never worked.
     *
     * @return array{orderby: ?string, order: ?string}
     */
    function sw_get_sort_params(): array
    {
        $allowed_orderby = ['date', 'title', 'modified', 'relevance'];

        $orderby = null;
        if (isset($_GET['sortby'])) {
            $candidate = strtolower(sanitize_text_field(wp_unslash($_GET['sortby'])));
            if (in_array($candidate, $allowed_orderby, true)) {
                $orderby = $candidate;
            }
        }

        $order = null;
        if (isset($_GET['order'])) {
            $candidate = strtoupper(sanitize_text_field(wp_unslash($_GET['order'])));
            if (in_array($candidate, ['ASC', 'DESC'], true)) {
                $order = $candidate;
            }
        }

        return ['orderby' => $orderby, 'order' => $order];
    }
}
