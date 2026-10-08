<?php

/**
 * Block: starwishx/accordion-opportunities-item — server render (front end).
 *
 * One <li> of the parent's ordered list: the category name links to its
 * listing (sw_listing_category_url(), the Listing module's URL scheme), a
 * number button toggles the item on touch screens (aria-expanded /
 * aria-controls; the "01" is a CSS counter, the aria-label is the name), and
 * the content row holds the description and a photo that only ever displays
 * on hover-capable screens ≥1024px (style.scss), so it is lazy and sized for
 * that width only.
 *
 * The term comes from the taxonomy's top-level list rather than get_term():
 * WP_Term_Query caches the list per request, so N items cost one query.
 *
 * Must output exactly ONE root element and never an empty string (a row
 * without a valid category renders hidden). No function declarations here —
 * WordPress `require`s this file once per block instance.
 *
 * @var array<string, mixed> $attributes Block attributes (defaults applied by WP_Block).
 * @var string               $content    Unused (dynamic block).
 * @var WP_Block             $block      Block instance.
 *
 * File: inc/blocks/accordion-opportunities-item/render.php
 */

declare(strict_types=1);

$taxonomy    = 'category-oportunities';
$term_id     = (int) ($attributes['termId'] ?? 0);
$description = trim((string) ($attributes['description'] ?? ''));
$photo_id    = (int) ($attributes['photoId'] ?? 0);

$term  = null;
$roots = $term_id > 0 ? get_terms(['taxonomy' => $taxonomy, 'parent' => 0, 'hide_empty' => false]) : [];
if (is_array($roots)) {
    foreach ($roots as $root) {
        if ((int) $root->term_id === $term_id) {
            $term = $root;
            break;
        }
    }
}

if (! $term) {
    echo '<li ' . get_block_wrapper_attributes(['class' => 'accordion-opportunities__item', 'hidden' => 'hidden']) . '></li>';
    return;
}

$item_id  = wp_unique_id('accordion-opportunities-item-');
$panel_id = $item_id . '-panel';

$inline_allowed = [
    'br'     => [],
    'strong' => [],
    'em'     => [],
    'a'      => ['href' => [], 'rel' => [], 'target' => []],
];

$photo_html = $photo_id > 0
    ? wp_get_attachment_image($photo_id, 'large', false, [
        'class'   => 'accordion-opportunities__image',
        'loading' => 'lazy',
        'sizes'   => '(min-width: 1920px) 240px, 225px',
    ])
    : '';

$wrapper = get_block_wrapper_attributes([
    'class'                   => 'accordion-opportunities__item',
    'data-id'                 => $item_id,
    'data-wp-class--is-active' => 'state.isActive',
]);
$context = wp_interactivity_data_wp_context(['id' => $item_id]);

/* translators: %s: opportunity category name */
$toggle_label = sprintf(__('Show details: %s', 'starwishx'), $term->name);
?>
<li <?php echo $wrapper; // escaped by get_block_wrapper_attributes() ?> <?php echo $context; // escaped by wp_interactivity_data_wp_context() ?>>
    <div class="accordion-opportunities__heading-row">
        <button type="button" class="accordion-opportunities__toggle" aria-controls="<?php echo esc_attr($panel_id); ?>" aria-expanded="false" aria-label="<?php echo esc_attr($toggle_label); ?>" data-wp-bind--aria-expanded="state.isActive" data-wp-on--click="actions.toggle"></button>
        <h3 class="h5 accordion-opportunities__item-title">
            <a href="<?php echo esc_url(sw_listing_category_url($term->slug)); ?>"><?php echo esc_html($term->name); ?></a>
        </h3>
    </div>
    <div class="accordion-opportunities__content" id="<?php echo esc_attr($panel_id); ?>">
        <div class="accordion-opportunities__body">
            <?php if ($description !== '') : ?>
                <p class="accordion-opportunities__description"><?php echo wp_kses($description, $inline_allowed); ?></p>
            <?php endif; ?>
        </div>
        <?php if ($photo_html !== '') : ?>
            <figure class="accordion-opportunities__photo"><?php echo $photo_html; // escaped by wp_get_attachment_image() ?></figure>
        <?php endif; ?>
    </div>
</li>
