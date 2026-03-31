<?php

/**
 * The template for displaying Projects archive
 *
 * Simple adaptive grid of project cards.
 * Uses native WP fields (post_title, post_content, thumbnail).
 *
 * Query budget: 1 (main WP_Query via pre_get_posts).
 *
 * File: archive-project.php
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$post_type = 'project';

// ── Main query adjustment (let WP handle pagination) ────────────────────────
add_action('pre_get_posts', function (WP_Query $query) use ($post_type): void {
    if (!$query->is_main_query() || is_admin()) {
        return;
    }
    if ($query->get('post_type') !== $post_type) {
        return;
    }
    $query->set('posts_per_page', 12);
    $query->set('orderby', 'date');
    $query->set('order', 'DESC');
});

get_header();
?>
<?php
if (function_exists('render_block')) {
    echo render_block([
        'blockName'   => 'acf/breadcrumbs',
        'attrs'       => [
            'data'        => [
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

<main class="archive-projects">
    <div class="container">
        <h1 class="h3 archive-projects__title"><?php esc_html_e('Projects', 'starwishx'); ?></h1>
        <?php
        $archive_description = get_the_archive_description();
        if (! empty($archive_description)) : ?>
            <div class="archive-projects__description">
                <?= $archive_description; ?>
            </div>
        <?php endif; ?>
        <?php if (have_posts()) : ?>
            <div class="archive-projects__grid">
                <?php while (have_posts()) : the_post();
                    $post_id   = get_the_ID();
                    $permalink = get_permalink();
                    $title     = get_the_title();
                    $excerpt   = get_the_excerpt();
                    $date      = get_the_date('d.m.Y');
                    $iso_date  = get_the_date('Y-m-d');

                    if (has_post_thumbnail($post_id)) {
                        $thumb_url = get_the_post_thumbnail_url($post_id, 'medium_large');
                        $thumb_alt = get_the_post_thumbnail_caption($post_id) ?: $title;
                    } else {
                        $thumb_url = get_template_directory_uri() . '/assets/img/card-placeholder.png';
                        $thumb_alt = $title;
                    }
                ?>
                    <article class="project-card">
                        <a href="<?= esc_url($permalink); ?>" class="project-card__image-link" rel="bookmark">
                            <figure class="project-card__figure">
                                <img src="<?= esc_url($thumb_url); ?>"
                                    class="project-card__img"
                                    alt="<?= esc_attr($thumb_alt); ?>"
                                    loading="lazy">
                            </figure>
                        </a>

                        <div class="project-card__body">
                            <time class="text-small project-card__date"
                                datetime="<?= esc_attr($iso_date); ?>">
                                <?= esc_html($date); ?>
                            </time>

                            <h2 class="project-card__title">
                                <a href="<?= esc_url($permalink); ?>" rel="bookmark">
                                    <?= esc_html($title); ?>
                                </a>
                            </h2>

                            <?php if ($excerpt) : ?>
                                <p class="text-r project-card__excerpt">
                                    <?= esc_html($excerpt); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <?php the_posts_pagination([
                'mid_size'  => 2,
                'prev_text' => sw_svg('icon-arrow', 10) . '<span class="screen-reader-text">' . esc_html__('Previous', 'starwishx') . '</span>',
                'next_text' => '<span class="screen-reader-text">' . esc_html__('Next', 'starwishx') . '</span>' . sw_svg('icon-arrow', 10),
            ]); ?>

        <?php else : ?>
            <p class="archive-projects__empty">
                <?php esc_html_e('No projects found.', 'starwishx'); ?>
            </p>
        <?php endif; ?>

    </div>
</main>

<?php get_footer(); ?>