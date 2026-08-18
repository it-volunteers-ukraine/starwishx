<?php

/**
 * News by category — legacy ACF block.
 *
 * Renders /news/news-by-category/{slug}/ (page 717). Superseded in PR 2 by
 * templates/archive-news-category.php on /news/{slug}/ — kept working here so
 * the card and query fixes land without a URL change.
 */

$default_classes = [
    'section'   => 'section',
    'title'     => 'title',
    'content'   => 'content',
    'newscards' => 'newscards',
];

$taxonomy      = my_category();
$category_slug = get_query_var('news_cat');
$term          = $category_slug ? my_category_by_slug($category_slug) : null;

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes      = $default_classes;

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['news-page-category'] ?? []);
}

// Цвета меток категорий — CSS-классы по slug термина
$css = sw_get_taxonomy_top_level_colors_styles($taxonomy);
if (!empty($css)) {
    wp_register_style('cat-oportunities-color-styles', false);
    wp_enqueue_style('cat-oportunities-color-styles');
    wp_add_inline_style('cat-oportunities-color-styles', $css);
}

$block_title = (string) get_field('title');
$title       = $term
    ? $block_title . ' "' . $term->name . '"'
    : $block_title;

$query = $term
    ? my_query_search(my_query_args_prepare([]))
    : null;

$posts = $query ? $query->posts : [];

if ($query) {
    // Блок пагинации читает этот запрос вместо собственного
    sw_set_pagination_context($query, (int) $query->get('posts_per_page'));
    update_post_thumbnail_cache($query);
}

?>

<section class="section <?php echo esc_attr($classes['section']); ?>">
    <div class="container">
        <h1 class="h3 <?php echo esc_attr($classes['title']); ?>"><?php echo esc_html($title); ?></h1>

        <?php if (!$term) : ?>
            <p><?php esc_html_e('Category is not specified.', 'starwishx'); ?></p>
        <?php elseif (!$posts) : ?>
            <p><?php esc_html_e('No news in this category yet.', 'starwishx'); ?></p>
        <?php else : ?>
            <div class="cards-list <?php echo esc_attr($classes['newscards']); ?>">
                <?php foreach ($posts as $post_item) : ?>
                    <?php get_template_part('template-parts/news-card', null, [
                        'post'         => $post_item,
                        'show_excerpt' => true,
                    ]); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
