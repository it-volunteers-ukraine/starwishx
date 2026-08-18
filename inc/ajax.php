<?php

/**
 * "Load more" endpoint for the legacy pagination block.
 *
 * Заменяется REST-маршрутом news/v1/posts в PR 3. Здесь закрыты дыры,
 * из-за которых обработчик принимал post_type от клиента и отдавал
 * страницы с другой сортировкой, чем первая.
 */

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_load_news', 'ajax_load_news');
add_action('wp_ajax_nopriv_load_news', 'ajax_load_news');

function ajax_load_news()
{
    check_ajax_referer('sw_load_news');

    $current_path = isset($_GET['current_path'])
        ? sanitize_text_field(wp_unslash($_GET['current_path']))
        : '';

    // post_type выводится из пути, а не из запроса: раньше сюда приходил
    // json_decode($_GET['post_type']) и уходил прямо в WP_Query
    $post_type = my_post_type($current_path);

    $taxonomy      = my_category();
    $category_slug = isset($_GET['category_slug'])
        ? sanitize_title(wp_unslash($_GET['category_slug']))
        : '';

    $no_desc      = isset($_GET['nodesc']) && $_GET['nodesc'] === '1';
    $card_version = isset($_GET['card_version']) ? (int) $_GET['card_version'] : 1;

    // Те же аргументы, что и у первой страницы: $_GET несёт search,
    // sortby, order, per_page и page_num
    $args = my_query_args_prepare(['post_type' => $post_type]);

    if ($category_slug !== '') {
        $args['tax_query'] = [[
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => $category_slug,
        ]];
    }

    $query = my_query_search($args);

    $term      = $category_slug !== '' ? my_category_by_slug($category_slug) : null;
    $term_id   = $term ? (int) $term->term_id : null;
    $term_name = $term ? $term->name : null;

    $total_posts = (int) $query->found_posts;
    $total_pages = (int) $query->max_num_pages;

    update_post_thumbnail_cache($query);

    ob_start();

    foreach ($query->posts as $post_item) {
        get_template_part('template-parts/news-card', null, [
            'post'            => $post_item,
            'show_excerpt'    => !$no_desc,
            'post_type_label' => $post_item->post_type_name ?? '',
            'card_version'    => $card_version,
        ]);
    }

    $html = ob_get_clean();

    wp_send_json_success([
        'html'          => $html,
        'post_type'     => $post_type,
        'category'      => $taxonomy,
        'category_slug' => $category_slug,
        'total_posts'   => $total_posts,
        'total_pages'   => $total_pages,
        'post_count'    => (int) $query->post_count,
        'page'          => (int) ($args['paged'] ?? 1),
        'term_id'       => $term_id,
        'term_name'     => $term_name,
        'search'        => $args['s'] ?? '',
    ]);
}
