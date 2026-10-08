<?php

/**
 * Block: starwishx/animated-text — server render (front end).
 *
 * A rounded, tinted section holding a large statement: the inner blocks
 * (paragraphs, headings) arrive rendered in $content. Not animated today; the
 * name is kept from the ACF block editors know, and this is where an
 * animation would live if it ever comes back.
 *
 * Must output exactly ONE root element and never an empty string. With no
 * content the front end gets a hidden root; the editor renders its own markup
 * (editor.js). No function declarations here — WordPress `require`s this file
 * once per block instance.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Rendered inner blocks.
 * @var WP_Block             $block      Block instance.
 *
 * File: inc/blocks/animated-text/render.php
 */

declare(strict_types=1);

$has_content = trim($content) !== '';

$wrapper_args = ['class' => 'section animated-text'];
if (! $has_content && ! wp_is_serving_rest_request()) {
    $wrapper_args['hidden'] = 'hidden';
}
$wrapper = get_block_wrapper_attributes($wrapper_args);
?>
<section <?php echo $wrapper; // escaped by get_block_wrapper_attributes() ?>>
    <div class="container">
        <div class="animated-text__content">
            <?php echo $content; // rendered core blocks, escaped by their own save ?>
        </div>
    </div>
</section>
