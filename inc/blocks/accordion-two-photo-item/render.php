<?php

/**
 * Block: starwishx/accordion-two-photo-item — server render (front end).
 *
 * One <li> of the parent's ordered list: sticky heading (the "01" comes from
 * a CSS counter), text, two portrait photos and a decorative stars icon.
 * Photos go through wp_get_attachment_image() with a `sizes` that matches
 * their rendered widths (120px → 14.583vw → 225px → 240px), so browsers pick
 * the 300px "medium" file rather than the 1024px one; alt text comes from
 * the Media Library. The text is RichText HTML and is sanitised with the
 * inline allowlist — the ACF template echoed it raw.
 *
 * Must output exactly ONE root element and never an empty string. No function
 * declarations here — WordPress `require`s this file once per block instance.
 *
 * @var array<string, mixed> $attributes Block attributes (defaults applied by WP_Block).
 * @var string               $content    Unused (dynamic block).
 * @var WP_Block             $block      Block instance.
 *
 * File: inc/blocks/accordion-two-photo-item/render.php
 */

declare(strict_types=1);

$title     = trim((string) ($attributes['title'] ?? ''));
$text      = trim((string) ($attributes['text'] ?? ''));
$photo_ids = [
    1 => (int) ($attributes['photo1Id'] ?? 0),
    2 => (int) ($attributes['photo2Id'] ?? 0),
];

$inline_allowed = [
    'br'     => [],
    'strong' => [],
    'em'     => [],
    'a'      => ['href' => [], 'rel' => [], 'target' => []],
];

$sizes = '(min-width: 1920px) 240px, (min-width: 1440px) 225px, (min-width: 768px) 14.583vw, 120px';

$photos_html = '';
foreach ($photo_ids as $slot => $photo_id) {
    if ($photo_id <= 0) {
        continue;
    }
    // '' when the attachment was deleted or is not an image → slot omitted.
    $photos_html .= wp_get_attachment_image($photo_id, 'large', false, [
        'class'   => 'accordion-two-photo__photo accordion-two-photo__photo--' . $slot,
        'loading' => 'lazy',
        'sizes'   => $sizes,
    ]);
}

$wrapper = get_block_wrapper_attributes(['class' => 'accordion-two-photo__item']);
?>
<li <?php echo $wrapper; // escaped by get_block_wrapper_attributes() ?>>
    <?php if ($title !== '') : ?>
        <div class="accordion-two-photo__heading">
            <h2 class="h2-big accordion-two-photo__title"><?php echo wp_kses($title, []); ?></h2>
        </div>
    <?php endif; ?>
    <div class="accordion-two-photo__content">
        <?php if ($text !== '') : ?>
            <p class="accordion-two-photo__text"><?php echo wp_kses($text, $inline_allowed); ?></p>
        <?php endif; ?>
        <div class="accordion-two-photo__photos">
            <?php echo $photos_html; // escaped by wp_get_attachment_image() ?>
            <!-- < ?php sw_svg_e('icon-stars-gradient', 24, 24, 'accordion-two-photo__icon'); // kses-escaped, aria-hidden ? > -->
            <img class="accordion-two-photo__icon" width="24" height="24" aria-hidden="true" src="<?= get_template_directory_uri(); ?>/assets/img/icon-stars-gradient.svg" alt="icon stars" loading="lazy">
        </div>
    </div>
</li>
