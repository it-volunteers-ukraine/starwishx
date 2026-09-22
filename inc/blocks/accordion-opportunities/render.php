<?php

/**
 * Block: starwishx/accordion-opportunities — server render (front end).
 *
 * Eyebrow, heading and "show all" button (defaults to the opportunity
 * archive), then an ordered list of starwishx/accordion-opportunities-item
 * children ($content, already rendered). The list is an Interactivity API
 * region: this element carries the context every item shares —
 * `activeIds` (which items are open) and `clickForTouch` (touch screens:
 * tap the number to open vs. open while scrolling) — see build/view.js.
 * Hover / keyboard reveal on mouse devices is pure CSS (style.scss).
 *
 * Must output exactly ONE root element and never an empty string. With no
 * items the front end gets a hidden root; the editor renders its own markup
 * (editor.js). No function declarations here — WordPress `require`s this file
 * once per block instance.
 *
 * @var array<string, mixed> $attributes Block attributes (defaults applied by WP_Block).
 * @var string               $content    Rendered inner blocks (items).
 * @var WP_Block             $block      Block instance.
 *
 * File: inc/blocks/accordion-opportunities/render.php
 */

declare(strict_types=1);

$suptitle    = trim((string) ($attributes['suptitle'] ?? ''));
$header      = trim((string) ($attributes['header'] ?? ''));
$button_text = trim((string) ($attributes['buttonText'] ?? ''));
$button_url  = trim((string) ($attributes['buttonUrl'] ?? ''));

if ($button_url === '') {
    $button_url = (string) (get_post_type_archive_link('opportunity') ?: '');
}
$has_button = $button_text !== '' && $button_url !== '';
$has_items  = trim($content) !== '';

$title_id = $header !== '' ? wp_unique_id('accordion-opportunities-title-') : '';

$wrapper_args = [
    'class'              => 'section accordion-opportunities',
    'data-wp-interactive' => 'starwishx/accordion-opportunities',
];
if ($title_id !== '') {
    $wrapper_args['aria-labelledby'] = $title_id;
}
if (! $has_items && ! wp_is_serving_rest_request()) {
    $wrapper_args['hidden'] = 'hidden';
}
$wrapper = get_block_wrapper_attributes($wrapper_args);

$context = wp_interactivity_data_wp_context([
    'activeIds'     => [],
    'clickForTouch' => ! empty($attributes['clickForTouch']),
]);

// Server-side twin of the JS `state.isActive` getter (build/view.js), so the
// directives are pre-rendered correctly (aria-expanded="false", no .is-active)
// instead of being stripped until hydration. Closures never reach the client.
wp_interactivity_state('starwishx/accordion-opportunities', [
    'isActive' => static function (): bool {
        $ctx = wp_interactivity_get_context();
        return in_array((string) ($ctx['id'] ?? ''), (array) ($ctx['activeIds'] ?? []), true);
    },
]);
?>
<section <?php echo $wrapper; // escaped by get_block_wrapper_attributes() ?> <?php echo $context; // escaped by wp_interactivity_data_wp_context() ?>>
    <div class="container">
        <?php if ($suptitle !== '' || $header !== '' || $has_button) : ?>
            <header class="accordion-opportunities__header">
                <?php if ($suptitle !== '') : ?>
                    <p class="accordion-opportunities__suptitle"><?php echo esc_html($suptitle); ?></p>
                <?php endif; ?>
                <?php if ($header !== '' || $has_button) : ?>
                    <div class="accordion-opportunities__heading">
                        <?php if ($header !== '') : ?>
                            <h2 id="<?php echo esc_attr($title_id); ?>" class="h2-big accordion-opportunities__title"><?php echo esc_html($header); ?></h2>
                        <?php endif; ?>
                        <?php if ($has_button) : ?>
                            <a href="<?php echo esc_url($button_url); ?>" class="btn accordion-opportunities__button accordion-opportunities__button--header"><?php echo esc_html($button_text); ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <ol class="accordion-opportunities__list" data-wp-init="callbacks.initList">
            <?php echo $content; // rendered child blocks, escaped by their own render.php ?>
        </ol>

        <?php if ($has_button) : ?>
            <a href="<?php echo esc_url($button_url); ?>" class="btn accordion-opportunities__button accordion-opportunities__button--footer"><?php echo esc_html($button_text); ?></a>
        <?php endif; ?>
    </div>
</section>
