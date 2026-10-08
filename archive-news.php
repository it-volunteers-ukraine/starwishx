<?php

/**
 * The template for displaying News archive
 *
 * Replaces the old ACF block (inc/acf/blocks/news-page/news-page.php).
 * Uses native WP fields (post_title, post_content, thumbnail) instead of ACF.
 * Category label colors injected as CSS classes via sw_get_taxonomy_top_level_colors_styles().
 *
 * Query budget: ~N+2 total (N = number of top-level categories, typically 3-5).
 *
 * File: archive-news.php
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$taxonomy  = 'category-oportunities';
$post_type = 'news';

// ── Category color CSS (classes keyed by term slug) ─────────────────────────
$css = sw_get_taxonomy_top_level_colors_styles($taxonomy);
if (!empty($css)) {
    wp_register_style('cat-oportunities-color-styles', false);
    wp_enqueue_style('cat-oportunities-color-styles', false);
    wp_add_inline_style('cat-oportunities-color-styles', $css);
}

// ── 1. Top-level categories ─────────────────────────────────────────────────
$terms = get_terms([
    'taxonomy'   => $taxonomy,
    'parent'     => 0,
    'hide_empty' => true,
    'orderby'    => 'name',
    'order'      => 'ASC',
]);

if (is_wp_error($terms)) {
    $terms = [];
}

// ── 2. Latest 8 news (sidebar) ─────────────────────────────────────────────
$latest_query = new WP_Query([
    'post_type'              => $post_type,
    'posts_per_page'         => 8,
    'orderby'                => 'date',
    'order'                  => 'DESC',
    'no_found_rows'          => true,          // skip SQL_CALC_FOUND_ROWS
    'update_post_meta_cache' => false,         // sidebar only needs title + date
    'tax_query'              => [[
        'taxonomy' => $taxonomy,
        'operator' => 'EXISTS',
    ]],
]);

$sidebar_posts = $latest_query->posts;
wp_reset_postdata();

// ── 3. News by category (up to 7 per category) ─────────────────────────────
$by_category = [];

foreach ($terms as $term) {
    $cat_query = new WP_Query([
        'post_type'      => $post_type,
        'posts_per_page' => 7,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
        'tax_query'      => [[
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => $term->term_id,
        ]],
    ]);

    if (!$cat_query->have_posts()) {
        wp_reset_postdata();
        continue;
    }

    // Batch-load thumbnail metadata for all posts in this category
    update_post_thumbnail_cache($cat_query);

    // Attach term data to each post (term cache already primed by WP_Query)
    foreach ($cat_query->posts as $p) {
        $p->term_name = $term->name;
        $p->term_slug = $term->slug;
    }

    $by_category[] = [
        'term'  => $term,
        'posts' => $cat_query->posts,
        'url'   => sw_news()->categoryUrl($term->slug),
    ];

    wp_reset_postdata();
}

// "One per category" — first post from each group (for top-section cards)
$one_per_category = array_map(fn($cat) => $cat['posts'][0], $by_category);

// On phones the cards form a native scroll-snap row (_page-news.scss); its
// position dots need a few lines of JS, loaded only when there is a row.
$card_count = count($one_per_category);
if ($card_count > 1) {
    wp_enqueue_script('sw-scroll-dots');
}

get_header();
?>
<?php
sw_breadcrumbs();
?>
<section class="news-archive">
    <div class="container">
        <h1 class="h3 title"><?php esc_html_e('News', 'starwishx'); ?></h1>

        <div class="lastnews-content">
            <div class="aside">
                <?php get_template_part('template-parts/news-aside', null, [
                    'posts' => $sidebar_posts,
                ]); ?>
            </div>

            <?php if ($one_per_category) : ?>
                <?php // One list for both layouts: a snap row on phones, grid items from 768px. role="list" keeps list semantics in Safari (list-style: none, display: contents). ?>
                <ul class="newscards" id="newscards-track" role="list">
                    <?php foreach ($one_per_category as $item) : ?>
                        <li class="newscards__item">
                            <?php get_template_part('template-parts/news-card', null, [
                                'post' => $item,
                            ]); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($card_count > 1) : ?>
                    <div class="newscards__dots" data-scroll-dots="newscards-track" role="group" aria-label="<?php esc_attr_e('News', 'starwishx'); ?>" hidden>
                        <?php foreach ($one_per_category as $i => $item) : ?>
                            <button type="button" class="newscards__dot" aria-label="<?php echo esc_attr(sprintf(
                                /* translators: 1: card number, 2: number of cards */
                                __('News %1$d of %2$d', 'starwishx'),
                                $i + 1,
                                $card_count
                            )); ?>"<?php echo $i === 0 ? ' aria-current="true"' : ''; ?>></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</section>

<?php if (!empty($by_category)) : ?>
    <div>
        <?php foreach ($by_category as $cat_data) : ?>
            <section class="section bycat-section">
                <div class="container">
                    <a href="<?php echo esc_url($cat_data['url']); ?>" class="cat-link">
                        <h2 class="h5 cat-title"><?php echo esc_html($cat_data['term']->name); ?></h2>
                    </a>

                    <div class="bycat-content">
                        <div class="bycat-first-item">
                            <?php get_template_part('template-parts/news-card', null, [
                                'post'       => $cat_data['posts'][0],
                                'is_large'   => true,
                            ]); ?>
                        </div>

                        <?php if (count($cat_data['posts']) > 1) : ?>
                            <div class="bycat-other-item">
                                <?php for ($i = 1; $i < count($cat_data['posts']); $i++) : ?>
                                    <?php get_template_part('template-parts/news-card', null, [
                                        'post'       => $cat_data['posts'][$i],
                                        'show_image' => false,
                                        'is_large'   => true,
                                    ]); ?>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <a href="<?php echo esc_url($cat_data['url']); ?>" class="btn bycat-btn">
                        <?php esc_html_e('View all', 'starwishx'); ?>
                    </a>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php get_footer(); ?>