<?php

/**
 * Search results.
 *
 * Native WordPress search: /search/{term}/ via core's search permastruct.
 * Replaces the ACF `search-page` block that rendered a WP page off a
 * ?search= parameter — WordPress never knew that was a search, so it had
 * no is_search(), no pagination and no canonical.
 *
 * SiteSearchProvider::applySearchQuery() constrains post types and applies
 * per_page / sort to the main query, so this template just renders it.
 *
 * File: search.php
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$search_term = get_search_query();
$total_posts = (int) $GLOBALS['wp_query']->found_posts;

// Category label colours — CSS classes keyed by term slug
$css = sw_get_taxonomy_top_level_colors_styles(\News\Core\NewsCore::TAXONOMY);
if (!empty($css)) {
    wp_register_style('cat-oportunities-color-styles', false);
    wp_enqueue_style('cat-oportunities-color-styles');
    wp_add_inline_style('cat-oportunities-color-styles', $css);
}

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

<main id="primary" class="site-main">
    <section class="section search-section">
        <div class="container">
            <h1 class="h3 search-title">
                <?php
                printf(
                    /* translators: %s: search query. */
                    esc_html__('Search results "%s"', 'starwishx'),
                    esc_html($search_term)
                );
                ?>
            </h1>

            <?php get_template_part('template-parts/sortby-and-result-posts', null, [
                'total_posts' => $total_posts,
            ]); ?>

            <?php if (have_posts()) : ?>
                <div id="sw-results" class="cards-list sw-cards-grid">
                    <?php while (have_posts()) : the_post(); ?>
                        <?php
                        $post_item   = get_post();
                        $post_terms  = get_the_terms($post_item->ID, \News\Core\NewsCore::TAXONOMY);
                        $type_object = get_post_type_object($post_item->post_type);

                        if (!empty($post_terms) && !is_wp_error($post_terms)) {
                            $post_item->term_name = $post_terms[0]->name;
                            $post_item->term_slug = $post_terms[0]->slug;
                        }

                        get_template_part('template-parts/news-card', null, [
                            'post'            => $post_item,
                            'post_type_label' => $type_object->labels->singular_name ?? '',
                            'card_version'    => 2,
                        ]);
                        ?>
                    <?php endwhile; ?>
                </div>
            <?php else : ?>
                <p class="search-empty">
                    <?php esc_html_e('Nothing found. Try a different search term.', 'starwishx'); ?>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <?php get_template_part('template-parts/pagination'); ?>
</main>

<?php
wp_reset_postdata();
get_footer();
