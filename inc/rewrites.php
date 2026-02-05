<?php
if (!defined('ABSPATH')) exit;

/**
 * Rewrite rules for news:
 * 1) /news/{category}/       - категория
 * 2) /news/{slug}/           - одиночный пост
 */

// Инициализация rewrite
add_action('init', function () {

    $single_template_slug = 'news-template'; // страница для одиночного поста
    $category_page_slug = 'news-by-category'; // страница категорий
    $category_parent = 'news';

    // ----- 1) Для одиночных постов -----
    add_rewrite_rule(
        '^news/([^/]+)/?$',
        'index.php?pagename=' . $single_template_slug . '&news_slug=$matches[1]',
        'top'
    );

    // ----- 2) Для категорий -----
    add_rewrite_rule(
        "^{$category_parent}/{$category_page_slug}/([^/]+)/?$",
        "index.php?pagename={$category_parent}/{$category_page_slug}&news_cat=\$matches[1]",
        'top'
    );

});

// Добавляем query vars
add_filter('query_vars', function ($vars) {
    $vars[] = 'news_slug';
    $vars[] = 'news_cat';
    return $vars;
});
