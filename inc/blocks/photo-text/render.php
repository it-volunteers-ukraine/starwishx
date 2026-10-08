<?php

/**
 * Block: starwishx/photo-text — server render (front end and editor preview).
 *
 * Section with a title, a photo and a text column. The photo is emitted by
 * wp_get_attachment_image(): srcset + an explicit `sizes` (it never renders
 * wider than 544px, so phones don't download the 1024px file), width/height
 * for a stable layout, and core's lazy-loading heuristics. Its alt text comes
 * from the Media Library — the adjacent heading is not a description of it.
 *
 * Must output exactly ONE root element and never an empty string: the editor
 * (ServerSideRender) merges the block wrapper props into the first root tag.
 * No function declarations here — WordPress `require`s this file once per
 * block instance.
 *
 * @var array<string, mixed> $attributes Block attributes (defaults applied by WP_Block).
 * @var string               $content    Unused (dynamic block).
 * @var WP_Block             $block      Block instance.
 *
 * File: inc/blocks/photo-text/render.php
 */

declare(strict_types=1);

$title    = trim((string) ($attributes['title'] ?? ''));
$text     = trim((string) ($attributes['text'] ?? ''));
$photo_id = (int) ($attributes['photoId'] ?? 0);

// '' when the attachment was deleted or is not an image → media column omitted.
$photo_html = $photo_id > 0
    ? wp_get_attachment_image($photo_id, 'large', false, [
        'class' => 'photo-text__image',
        'sizes' => '(min-width: 768px) 544px, calc(100vw - 32px)',
    ])
    : '';

// Unique per instance so two blocks on a page never share an id; only emitted
// with a heading, so aria-labelledby never dangles.
$title_id = $title !== '' ? wp_unique_id('photo-text-title-') : '';

$wrapper_args = ['class' => 'section photo-text'];
if ($title_id !== '') {
    $wrapper_args['aria-labelledby'] = $title_id;
}
$wrapper = get_block_wrapper_attributes($wrapper_args);

$icon_bg = get_template_directory_uri() . '/assets/img/star1-bg.png';
?>
<section <?php echo $wrapper; // escaped by get_block_wrapper_attributes() ?>>
    <div class="container photo-text__container">
        <?php if ($title !== '') : ?>
            <h2 id="<?php echo esc_attr($title_id); ?>" class="h3 photo-text__title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        <div class="photo-text__content">
            <?php if ($photo_html !== '') : ?>
                <div class="photo-text__media"><?php echo $photo_html; // escaped by wp_get_attachment_image() ?></div>
            <?php endif; ?>
            <div class="photo-text__body">
                <?php if ($text !== '') : ?>
                    <p class="photo-text__text"><?php echo esc_html($text); ?></p>
                <?php endif; ?>
                <div class="photo-text__icon" aria-hidden="true">
                    <img class="photo-text__icon-bg" src="<?php echo esc_url($icon_bg); ?>" alt="" width="80" height="80">
                    <?php sw_svg_e('icon-element_planet_3-circle', 96, null, 'photo-text__icon-mask'); // kses-escaped, aria-hidden ?>
                </div>
            </div>
        </div>
    </div>
</section>
