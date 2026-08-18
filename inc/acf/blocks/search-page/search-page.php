<?php

/**
 * Search results — legacy ACF block.
 *
 * Renders the /search/ page (807) driven by ?search=. Superseded in PR 3 by
 * native WP search on search.php — kept working here so the card and query
 * fixes land without a URL change.
 */

$default_classes = [
    'section'      => 'section',
    'title'        => 'title',
    'block-filter' => 'block-filter',
    'filter'       => 'filter',
    'filter-title' => 'filter-title',
    'filter-data'  => 'filter-data',
    'sort-icon'    => 'sort-icon',
    'content'      => 'content',
    'newscards'    => 'newscards',
];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes      = $default_classes;

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['search-page'] ?? []);
}

// Цвета меток категорий — CSS-классы по slug термина
$css = sw_get_taxonomy_top_level_colors_styles(my_category());
if (!empty($css)) {
    wp_register_style('cat-oportunities-color-styles', false);
    wp_enqueue_style('cat-oportunities-color-styles');
    wp_add_inline_style('cat-oportunities-color-styles', $css);
}

$search_term = isset($_GET['search'])
    ? sanitize_text_field(wp_unslash($_GET['search']))
    : '';

$title = (string) get_field('title') . ' "' . $search_term . '"';

$query       = my_query_search(my_query_args_prepare([]));
$posts       = $query->posts;
$total_posts = (int) $query->found_posts;

// Блок пагинации читает этот запрос вместо собственного
sw_set_pagination_context($query, (int) $query->get('posts_per_page'));
update_post_thumbnail_cache($query);

$card_version = 2;

?>

<section class="section <?php echo esc_attr($classes['section']); ?>">
    <div class="container">
        <h1 class="h3 <?php echo esc_attr($classes['title']); ?>"><?php echo esc_html($title); ?></h1>

        <?php get_template_part('template-parts/sortby-and-result-posts', null, ['total_posts' => $total_posts]); ?>

        <?php if ($posts) : ?>
            <div class="cards-list <?php echo esc_attr($classes['newscards']); ?>">
                <?php foreach ($posts as $post_item) : ?>
                    <?php get_template_part('template-parts/news-card', null, [
                        'post'            => $post_item,
                        'post_type_label' => $post_item->post_type_name ?? '',
                        'card_version'    => $card_version,
                    ]); ?>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p><?php esc_html_e('Nothing found. Try a different search term.', 'starwishx'); ?></p>
        <?php endif; ?>
    </div>
</section>
