<?php

/**
 * Template Name: Single News View
 * Template Post Type: news
 *
 * Displays a single News CPT post.
 * Uses native WP fields (post_title, post_content, thumbnail) — no ACF.
 * Layout mirrors single-opportunity.php (figure + article + aside).
 *
 * File: templates/single-news.php
 */

declare(strict_types=1);

$post_id  = get_the_ID();
$taxonomy = 'category-oportunities';

// Category label — news posts have exactly one root-level category
$post_terms = get_the_terms($post_id, $taxonomy);
$term_name  = '';
$term_slug  = '';

if (!empty($post_terms) && !is_wp_error($post_terms)) {
    $term_name = $post_terms[0]->name;
    $term_slug = $post_terms[0]->slug;
}

// CSS category color styles (classes keyed by term slug)
$css = sw_get_taxonomy_top_level_colors_styles($taxonomy);
if (!empty($css)) {
    wp_register_style('cat-oportunities-color-styles', false);
    wp_enqueue_style('cat-oportunities-color-styles', false);
    wp_add_inline_style('cat-oportunities-color-styles', $css);
}

get_header();
?>

<?php
if (function_exists('render_block')) {
    echo render_block([
        'blockName'   => 'acf/breadcrumbs',
        'attrs'       => [
            'data'        => [
                'show_last_item' => false,
                'nowrap'         => true,
                'nav_class'      => 'container',
            ],
        ],
        'innerHTML'   => '',
        'innerBlocks' => [],
    ]);
}
?>

<div class="single-news__layout container">
    <main id="main" class="news-main" role="main">

        <figure class="featured-figure">
            <?php if ($term_name) : ?>
                <div class="info-card info-card__categories">
                    <span class="newcard-label <?php echo esc_attr($term_slug); ?>">
                        <?php echo esc_html($term_name); ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('large', [
                    'itemprop' => 'image',
                    'class'    => 'featured-figure__image',
                ]); ?>
            <?php else : ?>
                <div class="featured-image__placeholder">
                    <img class="card-image__fallback--icon"
                        src="<?php echo esc_url(get_template_directory_uri() . '/assets/img/card-placeholder.png'); ?>"
                        alt="">
                </div>
            <?php endif; ?>
            <figcaption class="d-none"><?php the_title(); ?></figcaption>
            <!-- < ?php if (get_post_status() === 'publish') {
                get_template_part('template-parts/control-favorites', null, [
                    'post_id' => $post_id,
                    'show_label' => false
                ]);
            } ?> -->
        </figure>

        <article class="news-article" itemscope itemtype="https://schema.org/NewsArticle">
            <header class="news-header">
                <h1 class="news-title h4" itemprop="headline">
                    <?php the_title(); ?>
                </h1>

                <div class="news-meta__wrapper">
                    <div class="news-meta">
                        <div class="info-card info-card--date">
                            <span class="info-card__title">
                                <?php sw_svg_e('icon-calendar', 18, 18, 'info-card__icon'); ?>
                                <!-- < ?= esc_html('Published', 'starwishx'); ?> -->
                            </span>
                            <time class="btn-text-medium"
                                datetime="<?= esc_attr(get_the_date('Y-m-d')); ?>"
                                itemprop="datePublished">
                                <?= esc_html(get_the_date('d.m.Y')); ?>
                            </time>
                        </div>
                    </div>

                    <div class="news-social">
                        <div class="news-social__share">
                            <span><?php esc_html_e('Social share', 'starwishx'); ?></span>
                            <?php sw_svg_e('icon-share', 18, 20, 'icon-share'); ?>
                        </div>
                        <!-- <div class="news-badges">
                            < ?php if (get_post_status() === 'publish') {
                                get_template_part('template-parts/control-favorites', null, ['post_id' => $post_id]);
                            } ?>
                        </div> -->
                    </div>
                </div>
            </header>

            <section class="news-content" itemprop="articleBody">
                <?php the_content(); ?>
            </section>
            <?php get_template_part('template-parts/comments', 'interactive'); ?>
        </article>
    </main>

    <aside class="news-aside">
        <div class="news-container">
            <?php get_template_part('template-parts/news-aside', null, [
                'title'       => __('News', 'starwishx'),
                'title_class' => 'h5',
                'count_news'  => 7,
                'line_clamp'  => 3,
            ]); ?>
        </div>
    </aside>
</div>

<?php get_footer(); ?>