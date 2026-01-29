<?php

add_action('wp_ajax_load_news', 'ajax_load_news');
add_action('wp_ajax_nopriv_load_news', 'ajax_load_news');


function ajax_load_news()
{
    // 💥 убираем ВСЁ, что вывелось до нас
    while (ob_get_level()) {
        ob_end_clean();
    }

    $taxonomy = 'category-oportunities';

    $post_type = $_GET['post_type'] ?? 'news';
    $page      = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = in_array((int) ($_GET['per_page'] ?? 12), [4, 8, 12])
        ? (int) $_GET['per_page']
        : 12;

    $category_slug = sanitize_text_field($_GET['category'] ?? '');
    $term = get_term_by('slug', $category_slug, $taxonomy);
    if ($term && !is_wp_error($term)) {
        $term_id   = $term->term_id; // ID категории
        $term_name = $term->name;    // Название категории
    }

    if (!$category_slug) {
        wp_send_json_error('No category');
    }

    // Параметры для запроса
    $args = [
        'post_type'      => $post_type,
        'paged'          => $page,
        'posts_per_page' => $per_page,
        'tax_query'      => [
            [
                'taxonomy' => $taxonomy, // Или твоя кастомная taxonomy
                'field'    => 'slug',
                'terms'    => $category_slug,
            ]
        ]
    ];

    $query = new WP_Query($args);


    if ($query->have_posts()) {
        // создаём массив для этой категории
        $res_by_cat[$term->term_id] = [
            'term_id'   => $term->term_id,
            'term_name' => $term->name,
            'posts'     => []
        ];

        foreach ($query->posts as $post_item) {
            // можно добавить данные категории внутрь поста
            $post_item->term_id   = $term_id;
            $post_item->term_name = $term_name;

            $res_by_cat[$term->term_id]['posts'][] = $post_item;
        }
    }

    $total_posts = (int) $query->found_posts;
    $total_pages = (int) ceil($total_posts / $per_page);
    $post_count = $query->post_count;


    ob_start();
    // echo 'OK';
    $posts = $query->posts;

    $count = 0;
    foreach ($posts as $item) {
        $count++;


        $item->term_id = $term_id;

    get_template_part(
        'template-parts/new-card',
        null,
        [
            'item' => $item,
        ]
    );
    }

    wp_reset_postdata();

    $html = ob_get_clean();

    wp_send_json_success([
        'html'        => $html,
        'post_type'  => $post_type,
        'category'  => $category_slug,
        'total_posts' => $total_posts,
        'total_pages' => $total_pages,
        'post_count'  => $post_count,
        'page'        => $page,
        'term_id'    => $term_id,
        'term_name'  => $term_name,
        'count'       => $count,
    ]);
    wp_die();
}
