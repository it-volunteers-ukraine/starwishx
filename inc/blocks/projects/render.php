<?php

/**
 * Block: starwishx/projects — server render (front end and editor preview).
 *
 * The latest `project` posts as a horizontally scrolling row of cards. The
 * carousel is native: a scroll-snap track (style.scss) that touch, trackpads,
 * wheels and keyboard focus all drive on their own; build/view.js only wires
 * the two arrow buttons and their end-of-track state. No slider library.
 *
 * Cards follow the theme's card pattern: the heading link carries the
 * accessible name, the whole card is clickable through a stretched link, the
 * image keeps its Media Library alt. One query, thumbnail cache primed, images
 * sized for the card widths (the ACF block served 1024px files for 300px
 * cards) and lazy — the row sits far below the fold and off-screen slides
 * load as they approach.
 *
 * Must output exactly ONE root element and never an empty string: the editor
 * (ServerSideRender) merges the block wrapper props into the first root tag.
 * With no projects the front end gets a hidden root; the editor preview gets
 * a short notice. No function declarations here — WordPress `require`s this
 * file once per block instance.
 *
 * @var array<string, mixed> $attributes Block attributes (defaults applied by WP_Block).
 * @var string               $content    Unused (dynamic block).
 * @var WP_Block             $block      Block instance.
 *
 * File: inc/blocks/projects/render.php
 */

declare(strict_types=1);

$suptitle = trim((string) ($attributes['suptitle'] ?? ''));
$title    = trim((string) ($attributes['title'] ?? ''));
$count    = (int) ($attributes['slidesCount'] ?? 8);
$count    = max(1, min(24, $count > 0 ? $count : 8));

$query = new WP_Query(apply_filters('sw_projects_block_query_args', [
    'post_type'              => 'project',
    'post_status'            => 'publish',
    'posts_per_page'         => $count,
    'orderby'                => 'date',
    'order'                  => 'DESC',
    'no_found_rows'          => true,
    'ignore_sticky_posts'    => true,
    'update_post_term_cache' => false,
]));
update_post_thumbnail_cache($query);

$projects  = $query->posts;
$is_editor = wp_is_serving_rest_request();

// Card widths per breakpoint (style.scss): 1.1 → 1.2 → 1.6 → 2.1 → 3.1 → 4.1 cards in view.
$image_sizes = '(min-width: 1920px) 460px, (min-width: 1350px) 24vw, (min-width: 1024px) 32vw, (min-width: 768px) 47vw, (min-width: 540px) 62vw, (min-width: 420px) 83vw, 90vw';

$title_id = $title !== '' ? wp_unique_id('projects-title-') : '';

$wrapper_args = ['class' => 'projects'];
if ($title_id !== '') {
    $wrapper_args['aria-labelledby'] = $title_id;
}
if (! $projects && ! $is_editor) {
    $wrapper_args['hidden'] = 'hidden';
}
$wrapper = get_block_wrapper_attributes($wrapper_args);
?>
<section <?php echo $wrapper; // escaped by get_block_wrapper_attributes() ?>>
    <?php if (! $projects) : ?>
        <?php if ($is_editor) : ?>
            <p class="projects__empty"><?php esc_html_e('No projects to show yet.', 'starwishx'); ?></p>
        <?php endif; ?>
    <?php else : ?>
        <header class="projects__header">
            <?php if ($suptitle !== '') : ?>
                <p class="projects__suptitle"><?php echo esc_html($suptitle); ?></p>
            <?php endif; ?>
            <?php if ($title !== '') : ?>
                <h2 id="<?php echo esc_attr($title_id); ?>" class="projects__title"><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
            <div class="projects__arrows">
                <button type="button" class="projects__arrow projects__arrow--prev" aria-label="<?php esc_attr_e('Previous projects', 'starwishx'); ?>" aria-disabled="true">
                    <?php sw_svg_e('icon-arrow-left', 40, null, 'projects__arrow-icon'); // kses-escaped, aria-hidden ?>
                </button>
                <button type="button" class="projects__arrow projects__arrow--next" aria-label="<?php esc_attr_e('Next projects', 'starwishx'); ?>">
                    <?php sw_svg_e('icon-arrow-right', 40, null, 'projects__arrow-icon'); // kses-escaped, aria-hidden ?>
                </button>
            </div>
        </header>

        <div class="projects__viewport" role="region" aria-roledescription="<?php esc_attr_e('carousel', 'starwishx'); ?>" aria-label="<?php echo esc_attr($title !== '' ? $title : __('Projects', 'starwishx')); ?>">
            <ul class="projects__track">
                <?php foreach ($projects as $project) : ?>
                    <?php
                    $thumb_id = (int) get_post_thumbnail_id($project);
                    $image    = $thumb_id > 0
                        ? wp_get_attachment_image($thumb_id, 'large', false, [
                            'class'   => 'projects__image',
                            'loading' => 'lazy',
                            'sizes'   => $image_sizes,
                        ])
                        : '';
                    ?>
                    <li class="projects__slide">
                        <article class="projects__card">
                            <?php if ($image !== '') : ?>
                                <figure class="projects__media"><?php echo $image; // escaped by wp_get_attachment_image() ?></figure>
                            <?php endif; ?>
                            <time class="projects__date" datetime="<?php echo esc_attr((string) get_the_date('c', $project)); ?>"><?php echo esc_html((string) get_the_date('d.m.Y', $project)); ?></time>
                            <h3 class="projects__card-title">
                                <a class="projects__link" href="<?php echo esc_url((string) get_permalink($project)); ?>" rel="bookmark"><?php echo esc_html(get_the_title($project)); ?></a>
                            </h3>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</section>
