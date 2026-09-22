<?php

/**
 * Block: starwishx/hero — server render (front end and editor preview).
 *
 * The first screen: page heading, subtitle, two calls to action and a
 * full-bleed background photo. The photo is the LCP element, so it is served
 * with fetchpriority="high" (core then omits lazy loading and keeps high
 * priority off every other image), an explicit sizes="100vw", and a
 * <picture> only when a portrait photo for phones exists. The same candidate
 * lists feed the <head> preload (Blocks\Support\HeroPreload via HeroMedia),
 * so the browser reuses what it already started downloading.
 *
 * The photo is a decorative backdrop — the heading carries the message — so
 * it is hidden from assistive technology. Links are derived, as the ACF block
 * did: the opportunity archive and the launchpad "add" form (guests get the
 * Menu module's auth gate on the latter).
 *
 * Must output exactly ONE root element and never an empty string. No function
 * declarations here — WordPress `require`s this file once per block instance.
 *
 * @var array<string, mixed> $attributes Block attributes (defaults applied by WP_Block).
 * @var string               $content    Unused (dynamic block).
 * @var WP_Block             $block      Block instance.
 *
 * File: inc/blocks/hero/render.php
 */

declare(strict_types=1);

use Blocks\Support\HeroMedia;

$title       = trim((string) ($attributes['title'] ?? ''));
$subtitle    = trim((string) ($attributes['subtitle'] ?? ''));
$browse_text = trim((string) ($attributes['browseText'] ?? ''));
$add_text    = trim((string) ($attributes['addText'] ?? ''));
$text_bottom = trim((string) ($attributes['textBottom'] ?? ''));

$sources = HeroMedia::sources((int) ($attributes['imageId'] ?? 0), (int) ($attributes['imageMobileId'] ?? 0));

$browse_url = (string) (get_post_type_archive_link('opportunity') ?: home_url('/opportunities/'));
$add_url    = home_url('/launchpad/?panel=opportunities&view=add');

$image_attrs = [
    'class'         => 'hero__image',
    'alt'           => '',
    'sizes'         => HeroMedia::SIZES,
    'fetchpriority' => 'high',
];

$is_empty = ! $sources && $title === '' && $subtitle === '' && $browse_text === '' && $add_text === '' && $text_bottom === '';
$title_id = $title !== '' ? wp_unique_id('hero-title-') : '';

$wrapper_args = ['class' => 'section hero'];
if ($title_id !== '') {
    $wrapper_args['aria-labelledby'] = $title_id;
}
if ($is_empty && ! wp_is_serving_rest_request()) {
    $wrapper_args['hidden'] = 'hidden';
}
$wrapper = get_block_wrapper_attributes($wrapper_args);
?>
<section <?php echo $wrapper; // escaped by get_block_wrapper_attributes() ?>>
    <?php if ($sources) : ?>
        <div class="hero__media" aria-hidden="true">
            <?php if (count($sources) > 1) : ?>
                <picture class="hero__picture">
                    <source media="<?php echo esc_attr((string) $sources[0]['media']); ?>" srcset="<?php echo esc_attr($sources[0]['srcset']); ?>" sizes="<?php echo esc_attr(HeroMedia::SIZES); ?>">
                    <?php echo wp_get_attachment_image($sources[1]['id'], 'large', false, $image_attrs); // escaped by wp_get_attachment_image() ?>
                </picture>
            <?php else : ?>
                <?php echo wp_get_attachment_image($sources[0]['id'], 'large', false, $image_attrs); // escaped by wp_get_attachment_image() ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="container">
        <div class="hero__content">
            <?php if ($title !== '') : ?>
                <h1 id="<?php echo esc_attr($title_id); ?>" class="h1 hero__title"><?php echo esc_html($title); ?></h1>
            <?php endif; ?>
            <?php if ($subtitle !== '') : ?>
                <p class="subtitle-text-r hero__subtitle"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>
            <?php if ($browse_text !== '' || $add_text !== '') : ?>
                <div class="hero__actions">
                    <?php if ($browse_text !== '') : ?>
                        <a class="h4 hero__link" href="<?php echo esc_url($browse_url); ?>"><?php echo esc_html($browse_text); ?></a>
                    <?php endif; ?>
                    <?php if ($add_text !== '') : ?>
                        <?php if (is_user_logged_in()) : ?>
                            <a class="h4 hero__link" href="<?php echo esc_url($add_url); ?>"><?php echo esc_html($add_text); ?></a>
                        <?php else : ?>
                            <a class="h4 hero__link" href="<?php echo esc_url($add_url); ?>" data-wp-interactive="menu" data-wp-on--click="actions.handleAuthGate"><?php echo esc_html($add_text); ?></a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($text_bottom !== '') : ?>
                <p class="text-r hero__text"><?php echo esc_html($text_bottom); ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>
