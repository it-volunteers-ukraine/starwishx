<?php

/**
 * Block: starwishx/news — server render (front end and editor preview).
 *
 * The newest news post of every top-level category as a card grid, with an
 * eyebrow label, a heading and a "show all" button that defaults to the news
 * archive (Polylang resolves the per-language archive). Data comes from
 * sw_news_latest_by_root_term() in the News module (inc/news/helpers.php),
 * which primes every cache the cards read, so the loop below runs no queries.
 *
 * Cards follow the inclusive card pattern: the heading link carries the
 * accessible name, the thumbnail is decorative unless its Media Library alt
 * says otherwise, and the whole card stays clickable through a stretched link
 * (see style.scss). Category chip colours come from the taxonomy colour sheet
 * (sw_get_taxonomy_top_level_colors_styles(), matched by data-slug).
 *
 * Must output exactly ONE root element and never an empty string: the editor
 * (ServerSideRender) merges the block wrapper props into the first root tag.
 * With nothing to show the front end gets a hidden root (no empty section
 * padding); the editor preview gets a short notice so the block stays
 * selectable. No function declarations here — WordPress `require`s this file
 * once per block instance.
 *
 * @var array<string, mixed> $attributes Block attributes (defaults applied by WP_Block).
 * @var string               $content    Unused (dynamic block).
 * @var WP_Block             $block      Block instance.
 *
 * File: inc/blocks/news/render.php
 */

declare(strict_types=1);

$taxonomy = 'category-oportunities';

$label       = trim((string) ($attributes['label'] ?? ''));
$title       = trim((string) ($attributes['title'] ?? ''));
$button_text = trim((string) ($attributes['buttonText'] ?? ''));
$button_url  = trim((string) ($attributes['buttonUrl'] ?? ''));

if ($button_url === '') {
    $button_url = (string) (get_post_type_archive_link('news') ?: '');
}
$has_button = $button_text !== '' && $button_url !== '';

$items     = sw_news_latest_by_root_term($taxonomy);
$is_editor = wp_is_serving_rest_request();

// Category chip colours (transient-cached CSS, hoisted into <head> by core).
// Guarded: an archive template may already have printed the same handle.
$colors_handle = 'cat-oportunities-color-styles';
if ($items && ! wp_style_is($colors_handle, 'enqueued')) {
    $colors_css = sw_get_taxonomy_top_level_colors_styles($taxonomy);
    if ($colors_css !== '') {
        wp_register_style($colors_handle, false);
        wp_enqueue_style($colors_handle);
        wp_add_inline_style($colors_handle, $colors_css);
    }
}

$title_id = $title !== '' ? wp_unique_id('news-title-') : '';

$wrapper_args = ['class' => 'section news'];
if ($title_id !== '') {
    $wrapper_args['aria-labelledby'] = $title_id;
}
if (! $items && ! $is_editor) {
    $wrapper_args['hidden'] = 'hidden';
}
$wrapper = get_block_wrapper_attributes($wrapper_args);

$placeholder = get_template_directory_uri() . '/assets/img/card-placeholder.png';
?>
<section <?php echo $wrapper; // escaped by get_block_wrapper_attributes() ?>>
    <div class="container">
        <?php if (! $items) : ?>
            <?php if ($is_editor) : ?>
                <p class="news__empty"><?php esc_html_e('No news to show yet.', 'starwishx'); ?></p>
            <?php endif; ?>
        <?php else : ?>
            <?php if ($label !== '' || $title !== '' || $has_button) : ?>
                <header class="news__header">
                    <?php if ($label !== '') : ?>
                        <p class="news__subtitle"><?php echo esc_html($label); ?></p>
                    <?php endif; ?>
                    <?php if ($title !== '' || $has_button) : ?>
                        <div class="news__heading">
                            <?php if ($title !== '') : ?>
                                <h2 id="<?php echo esc_attr($title_id); ?>" class="h2-big news__title"><?php echo esc_html($title); ?></h2>
                            <?php endif; ?>
                            <?php if ($has_button) : ?>
                                <a href="<?php echo esc_url($button_url); ?>" class="btn news__button news__button--header"><?php echo esc_html($button_text); ?></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </header>
            <?php endif; ?>

            <ul class="news__list">
                <?php foreach ($items as $index => $post_item) : ?>
                    <?php
                    // Cards 1 and 6 of every six span the full/half row (see style.scss),
                    // so their image needs up to twice the width of the others.
                    $is_wide = $index % 6 === 0 || $index % 6 === 5;
                    $sizes   = $is_wide
                        ? '(min-width: 1440px) 50vw, 100vw'
                        : '(min-width: 1440px) 25vw, (min-width: 576px) 50vw, 100vw';

                    $thumb_id = (int) get_post_thumbnail_id($post_item);
                    $image    = $thumb_id > 0
                        ? wp_get_attachment_image($thumb_id, 'large', false, [
                            'class'   => 'news__image',
                            'loading' => 'lazy',
                            'sizes'   => $sizes,
                        ])
                        : '';
                    if ($image === '') {
                        $image = sprintf(
                            '<img class="news__image" src="%s" alt="" width="600" height="600" loading="lazy" decoding="async">',
                            esc_url($placeholder)
                        );
                    }
                    ?>
                    <li class="news__item">
                        <div class="news__media">
                            <?php echo $image; // escaped by wp_get_attachment_image() / sprintf above ?>
                            <span class="news__label" data-slug="<?php echo esc_attr((string) ($post_item->term_slug ?? '')); ?>"><?php echo esc_html((string) ($post_item->term_name ?? '')); ?></span>
                        </div>
                        <time class="text-small news__date" datetime="<?php echo esc_attr((string) get_the_date('Y-m-d', $post_item)); ?>"><?php echo esc_html((string) get_the_date('d.m.Y', $post_item)); ?></time>
                        <h3 class="btn-text-medium news__item-title">
                            <a class="news__link" href="<?php echo esc_url((string) get_permalink($post_item)); ?>" rel="bookmark"><?php echo esc_html(get_the_title($post_item)); ?></a>
                        </h3>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($has_button) : ?>
                <a href="<?php echo esc_url($button_url); ?>" class="btn news__button news__button--footer"><?php echo esc_html($button_text); ?></a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
