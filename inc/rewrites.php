<?php
if (!defined('ABSPATH')) exit;

/**
 * Rewrite rules for pages with category in URL
 * /news/{category}/
 * /opportunities/{category}/
 *
 * Pagination and per_page are handled via GET params:
 * ?page=2&per_page=8
 */

add_action('init', function () {

    $pages = [
        'news-by-category' => [
            'query_var' => 'news_cat',
        ],
        'opportunities' => [
            'query_var' => 'event_cat',
        ],
    ];

    foreach ($pages as $slug => $config) {

        // /news/category-slug/
        add_rewrite_rule(
            "^{$slug}/([^/]+)/?$",
            "index.php?pagename={$slug}&{$config['query_var']}=\$matches[1]",
            'top'
        );
    }
});

/**
 * Register custom query vars
 */
add_filter('query_vars', function ($vars) {
    return array_merge($vars, [
        'news_cat',
        'event_cat',
    ]);
});
