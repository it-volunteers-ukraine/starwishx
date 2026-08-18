<?php

/**
 * News filtered by category — /news/{category}/
 *
 * Replaces the ACF block that used to render page 717 at
 * /news/news-by-category/{category}/.
 *
 * Driven entirely by the main query: NewsCore::applyCategoryArchiveQuery()
 * applies the taxonomy filter, per_page and sort in pre_get_posts, so
 * pagination comes from max_num_pages with no second query.
 *
 * File: templates/archive-news-category.php
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$taxonomy = \News\Core\NewsCore::TAXONOMY;
$slug     = (string) get_query_var(\News\Core\NewsCore::QUERY_VAR);
$term     = get_term_by('slug', $slug, $taxonomy);

// NewsCore::disambiguateCategoryUrl() guarantees a real term here; bail
// defensively rather than render a headless page if that ever changes.
if (!$term || is_wp_error($term)) {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    get_template_part('404');
    return;
}

// Category label colours — CSS classes keyed by term slug
$css = sw_get_taxonomy_top_level_colors_styles($taxonomy);
if (!empty($css)) {
    wp_register_style('cat-oportunities-color-styles', false);
    wp_enqueue_style('cat-oportunities-color-styles');
    wp_add_inline_style('cat-oportunities-color-styles', $css);
}

// Batch-load thumbnail metadata for the whole page of results
update_post_thumbnail_cache($GLOBALS['wp_query']);

get_header();
?>

<?php
if (function_exists('render_block')) {
    echo render_block([
        'blockName'   => 'acf/breadcrumbs',
        'attrs'       => [
            'data' => [
                'show_last_item' => true,
                'nowrap'         => true,
                'nav_class'      => 'container',
            ],
        ],
        'innerHTML'   => '',
        'innerBlocks' => [],
    ]);
}
?>

<section class="section newscat-section">
    <div class="container">
        <h1 class="h3 newscat-title">
            <?php
            printf(
                /* translators: %s: news category name. */
                esc_html__('News "%s"', 'starwishx'),
                esc_html($term->name)
            );
            ?>
        </h1>

        <?php if (have_posts()) : ?>
            <div id="sw-results" class="cards-list sw-cards-grid">
                <?php while (have_posts()) : the_post(); ?>
                    <?php
                    $post_item            = get_post();
                    $post_item->term_name = $term->name;
                    $post_item->term_slug = $term->slug;

                    get_template_part('template-parts/news-card', null, [
                        'post'         => $post_item,
                        'show_excerpt' => true,
                    ]);
                    ?>
                <?php endwhile; ?>
            </div>
        <?php else : ?>
            <p class="newscat-empty"><?php esc_html_e('No news in this category yet.', 'starwishx'); ?></p>
        <?php endif; ?>
    </div>
</section>

<?php get_template_part('template-parts/pagination'); ?>

<?php
wp_reset_postdata();
get_footer();
