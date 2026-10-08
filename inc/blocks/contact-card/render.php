<?php

/**
 * Block: starwishx/contact-card — server render (front end and editor preview).
 *
 * A photo, a heading and a short text next to the site's contact links. The
 * links are not block attributes: they come from Theme Settings → Common Info
 * through the Contact module (sw_contact_links()), the same source as the
 * contact section, so changing them there updates every card.
 *
 * The photo is emitted by wp_get_attachment_image(): srcset with an explicit
 * `sizes` (the box is 336/330/425px wide from tablet up, full width on phones),
 * width/height, and its alt from the Media Library — the heading next to it
 * is not a description of it. The spinning star is decoration (aria-hidden).
 *
 * Must output exactly ONE root element and never an empty string: the editor
 * (ServerSideRender) merges the block wrapper props into the first root tag.
 * With nothing to show, the front end gets a hidden root and the editor
 * preview a short notice. No function declarations here — WordPress
 * `require`s this file once per block instance.
 *
 * @var array<string, mixed> $attributes Block attributes (defaults applied by WP_Block).
 * @var string               $content    Unused (dynamic block).
 * @var WP_Block             $block      Block instance.
 *
 * File: inc/blocks/contact-card/render.php
 */

declare(strict_types=1);

$title    = trim((string) ($attributes['title'] ?? ''));
$text     = trim((string) ($attributes['text'] ?? ''));
$image_id = (int) ($attributes['imageId'] ?? 0);
$links    = function_exists('sw_contact_links') ? sw_contact_links() : [];

// '' when the attachment was deleted or is not an image → media column omitted.
// Eager: the card opens the contacts page, where the photo sits in the first
// viewport on phones and desktops alike (core's heuristics marked it lazy,
// delaying what is likely the page's largest paint).
$image_html = $image_id > 0
    ? wp_get_attachment_image($image_id, 'large', false, [
        'class'   => 'contact-card__image',
        'sizes'   => '(min-width: 1920px) 425px, (min-width: 768px) 336px, calc(100vw - 32px)',
        'loading' => 'eager',
    ])
    : '';

$has_content = $title !== '' || $text !== '' || $image_html !== '' || $links !== [];
$is_editor   = wp_is_serving_rest_request();

// Unique per instance so two cards on a page never share an id; only emitted
// with a heading, so aria-labelledby never dangles.
$title_id = $title !== '' ? wp_unique_id('contact-card-title-') : '';

$wrapper_args = ['class' => 'section contact-card'];
if ($title_id !== '') {
    $wrapper_args['aria-labelledby'] = $title_id;
}
if (! $has_content && ! $is_editor) {
    $wrapper_args['hidden'] = 'hidden';
}
$wrapper = get_block_wrapper_attributes($wrapper_args);

$img_dir = get_template_directory_uri() . '/assets/img/';
?>
<section <?php echo $wrapper; // escaped by get_block_wrapper_attributes() ?>>
    <?php if (! $has_content) : ?>
        <?php if ($is_editor) : ?>
            <p class="container contact-card__empty"><?php esc_html_e('Add a title, a text or a photo.', 'starwishx'); ?></p>
        <?php endif; ?>
    <?php else : ?>
        <div class="container contact-card__row">
            <?php if ($image_html !== '') : ?>
                <figure class="contact-card__media"><?php echo $image_html; // escaped by wp_get_attachment_image() ?></figure>
            <?php endif; ?>
            <div class="contact-card__body">
                <?php if ($title !== '') : ?>
                    <h2 id="<?php echo esc_attr($title_id); ?>" class="h5 contact-card__title"><?php echo esc_html($title); ?></h2>
                <?php endif; ?>
                <?php if ($text !== '') : ?>
                    <p class="contact-card__text"><?php echo esc_html($text); ?></p>
                <?php endif; ?>
                <?php if ($links !== []) : ?>
                    <address class="contact-card__contacts">
                        <ul class="contact-card__list">
                            <?php foreach ($links as $link) : ?>
                                <li class="contact-card__item">
                                    <?php echo sw_svg($link['icon'], 24, null, 'contact-card__item-icon'); // kses-escaped, aria-hidden ?>
                                    <span class="contact-card__label"><?php echo esc_html($link['label']); ?></span>
                                    <a class="contact-card__link" href="<?php echo esc_url($link['url']); ?>"<?php echo $link['external'] ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html($link['text']); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </address>
                <?php endif; ?>
                <div class="contact-card__icon" aria-hidden="true">
                    <img class="contact-card__icon-bg" src="<?php echo esc_url($img_dir . 'planet-bg-radial-gradient.svg'); ?>" alt="" width="96" height="96" decoding="async">
                    <img class="contact-card__icon-mask" src="<?php echo esc_url($img_dir . 'planet-mask-gradient.svg'); ?>" alt="" width="96" height="96" decoding="async">
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
