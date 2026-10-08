<?php

/**
 * Block: starwishx/accordion-two-photo — server render (front end).
 *
 * Container for starwishx/accordion-two-photo-item children: an ordered list
 * (the visible "01", "02"… numbering is a CSS counter on each item's heading,
 * the list itself gives assistive technology the position and count). The
 * children arrive already rendered in $content.
 *
 * Must output exactly ONE root element and never an empty string. With no
 * items the front end gets a hidden root (no empty section padding); the
 * editor renders its own markup (editor.js), so the REST case is only there
 * for completeness. No function declarations here — WordPress `require`s
 * this file once per block instance.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Rendered inner blocks (items).
 * @var WP_Block             $block      Block instance.
 *
 * File: inc/blocks/accordion-two-photo/render.php
 */

declare(strict_types=1);

$has_items = trim($content) !== '';

$wrapper_args = ['class' => 'section accordion-two-photo'];
if (! $has_items && ! wp_is_serving_rest_request()) {
    $wrapper_args['hidden'] = 'hidden';
}
$wrapper = get_block_wrapper_attributes($wrapper_args);
?>
<section <?php echo $wrapper; // escaped by get_block_wrapper_attributes() ?>>
    <div class="container accordion-two-photo__container">
        <ol class="accordion-two-photo__list">
            <?php echo $content; // rendered child blocks, escaped by their own render.php ?>
        </ol>
    </div>
</section>
