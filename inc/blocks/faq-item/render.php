<?php

/**
 * Block: starwishx/faq-item — server render (front end).
 *
 * One <li> of the FAQ list holding a native disclosure: <details> with the
 * question as <summary> (an <h3> inside it, which the summary content model
 * allows, so questions are headings too) and the answer's inner blocks.
 * The `name` attribute groups every item of the same parent instance into an
 * exclusive accordion — the id comes from Blocks\Support\InnerBlockContext,
 * never from post content — and the first item opens when the parent says so.
 * Opening/closing animates through ::details-content in style.scss; no
 * JavaScript anywhere.
 *
 * Must output exactly ONE root element and never an empty string (an empty
 * question renders hidden). No function declarations here — WordPress
 * `require`s this file once per block instance.
 *
 * @var array<string, mixed> $attributes Block attributes (defaults applied by WP_Block).
 * @var string               $content    Rendered inner blocks (the answer).
 * @var WP_Block             $block      Block instance (carries the context).
 *
 * File: inc/blocks/faq-item/render.php
 */

declare(strict_types=1);

$question = trim((string) ($attributes['question'] ?? ''));
$answer   = trim($content);

if ($question === '' && $answer === '') {
    echo '<li ' . get_block_wrapper_attributes(['class' => 'faq__item', 'hidden' => 'hidden']) . '></li>';
    return;
}

$group = (string) ($block->context['starwishx/parentId'] ?? 'faq');
$index = (int) ($block->context['starwishx/childIndex'] ?? 0);
$open  = ! empty($block->context['starwishx/faqOpenFirst']) && $index === 0;

$wrapper = get_block_wrapper_attributes(['class' => 'faq__item']);
?>
<li <?php echo $wrapper; // escaped by get_block_wrapper_attributes() ?>>
    <details class="faq__details" name="<?php echo esc_attr('faq-' . $group); ?>"<?php echo $open ? ' open' : ''; ?>>
        <summary class="faq__summary">
            <h3 class="faq__question"><?php echo wp_kses($question, []); ?></h3>
            <?php sw_svg_e('icon-plus', 24, 24, 'faq__icon'); // kses-escaped, aria-hidden ?>
        </summary>
        <div class="faq__answer">
            <?php echo $answer; // rendered core blocks (paragraphs, lists), escaped by their own save ?>
        </div>
    </details>
</li>
