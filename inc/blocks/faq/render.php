<?php

/**
 * Block: starwishx/faq — server render (front end).
 *
 * Eyebrow, heading and an ordered list of starwishx/faq-item children
 * ($content, already rendered). The accordion itself is native
 * <details>/<summary> markup inside the items — no script — and the
 * "open the first question" option reaches them as block context
 * (providesContext in block.json). FAQPage structured data is emitted by
 * Blocks\Support\FaqSchema from the post's blocks, not from this render.
 *
 * Must output exactly ONE root element and never an empty string. With no
 * items the front end gets a hidden root; the editor renders its own markup
 * (editor.js). No function declarations here — WordPress `require`s this file
 * once per block instance.
 *
 * @var array<string, mixed> $attributes Block attributes (defaults applied by WP_Block).
 * @var string               $content    Rendered inner blocks (questions).
 * @var WP_Block             $block      Block instance.
 *
 * File: inc/blocks/faq/render.php
 */

declare(strict_types=1);

$suptitle  = trim((string) ($attributes['suptitle'] ?? ''));
$title     = trim((string) ($attributes['title'] ?? ''));
$has_items = trim($content) !== '';

$title_id = $title !== '' ? wp_unique_id('faq-title-') : '';

$wrapper_args = ['class' => 'section faq'];
if ($title_id !== '') {
    $wrapper_args['aria-labelledby'] = $title_id;
}
if (! $has_items && ! wp_is_serving_rest_request()) {
    $wrapper_args['hidden'] = 'hidden';
}
$wrapper = get_block_wrapper_attributes($wrapper_args);
?>
<section <?php echo $wrapper; // escaped by get_block_wrapper_attributes() ?>>
    <div class="container">
        <div class="faq__inner">
            <?php if ($suptitle !== '') : ?>
                <p class="faq__suptitle"><?php echo esc_html($suptitle); ?></p>
            <?php endif; ?>
            <?php if ($title !== '') : ?>
                <h2 id="<?php echo esc_attr($title_id); ?>" class="h2-big faq__title"><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
            <ol class="faq__list">
                <?php echo $content; // rendered child blocks, escaped by their own render.php ?>
            </ol>
        </div>
    </div>
</section>
