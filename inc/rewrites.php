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
            'parent' => 'news'
        ],
    ];

    foreach ($pages as $slug => $config) {

        $parent = $config['parent'] ?? '';
        $pagename = $parent ? $parent . '/' . $slug : $slug;

        // Для страницы с категорией
        add_rewrite_rule(
            "^{$parent}/{$slug}/([^/]+)/?$",
            "index.php?pagename={$pagename}&{$config['query_var']}=\$matches[1]",
            'top'
        );
    }
});

// Query vars
add_filter('query_vars', function ($vars) {
    return array_merge($vars, [
        'news_cat',
    ]);
});

?>
