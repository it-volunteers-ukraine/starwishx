<?php

/**
 * Template Part: Social Share
 *
 * Renders the share popover (trigger + panel) using the Interactivity API.
 * Per-instance UI state lives in data-wp-context; static i18n / config in
 * the 'social-share' iAPI store (hydrated by SocialShareCore).
 *
 * Usage:
 *   get_template_part('template-parts/social-share', null, [
 *       'post_id' => $post_id,            // optional, default: get_the_ID()
 *       'post_url' => $url,               // optional, default: permalink
 *       'post_title' => $title,           // optional, default: post title
 *       'label' => __('Share', '...'),    // trigger label
 *       'copy_label' => __('Copy link'),  // copy button accessible name
 *       'group_label' => __('Share via'), // accessible name for the link group
 *       'wrapper_class' => 'extra-class', // additional class on the wrapper
 *       'trigger_class' => 'extra-class', // additional class on the trigger
 *   ]);
 *
 * File: template-parts/social-share.php
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$args = isset($args) && is_array($args) ? $args : [];

$post_id    = isset($args['post_id']) ? absint($args['post_id']) : (int) get_the_ID();
$post_url   = isset($args['post_url']) ? esc_url_raw((string) $args['post_url']) : (string) get_permalink($post_id);
$post_title = isset($args['post_title']) ? wp_strip_all_tags((string) $args['post_title']) : (string) get_the_title($post_id);

if (empty($post_url)) {
    return;
}

$label       = isset($args['label']) ? (string) $args['label'] : __('Social share', 'starwishx');
$copy_label  = isset($args['copy_label']) ? (string) $args['copy_label'] : __('Copy link', 'starwishx');
$group_label = isset($args['group_label']) ? (string) $args['group_label'] : __('Share via', 'starwishx');

$wrapper_extra = isset($args['wrapper_class']) ? sanitize_html_class((string) $args['wrapper_class']) : '';
$trigger_extra = isset($args['trigger_class']) ? sanitize_html_class((string) $args['trigger_class']) : '';

if (function_exists('social_share')) {
    social_share()->loadAssets();
}

$encoded_url   = rawurlencode($post_url);
$encoded_title = rawurlencode($post_title);
$encoded_pair  = rawurlencode($post_title . ' ' . $post_url);

$networks = [
    [
        'icon'  => 'icon-facebook',
        'name'  => __('Facebook', 'starwishx'),
        'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . $encoded_url,
        'aria'  => sprintf(__('Share on %s', 'starwishx'), __('Facebook', 'starwishx')),
    ],
    [
        'icon'  => 'icon-linkedin',
        'name'  => __('LinkedIn', 'starwishx'),
        'url'   => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $encoded_url,
        'aria'  => sprintf(__('Share on %s', 'starwishx'), __('LinkedIn', 'starwishx')),
    ],
    [
        'icon'  => 'icon-whatsapp',
        'name'  => __('WhatsApp', 'starwishx'),
        'url'   => 'https://wa.me/?text=' . $encoded_pair,
        'aria'  => sprintf(__('Share on %s', 'starwishx'), __('WhatsApp', 'starwishx')),
    ],
    [
        'icon'  => 'icon-viber',
        'name'  => __('Viber', 'starwishx'),
        'url'   => 'viber://forward?text=' . $encoded_pair,
        'aria'  => sprintf(__('Share on %s', 'starwishx'), __('Viber', 'starwishx')),
    ],
    [
        'icon'  => 'icon-telegram',
        'name'  => __('Telegram', 'starwishx'),
        'url'   => 'https://t.me/share/url?url=' . $encoded_url . '&text=' . $encoded_title,
        'aria'  => sprintf(__('Share on %s', 'starwishx'), __('Telegram', 'starwishx')),
    ],
    [
        'icon'  => 'icon-twitterx',
        'name'  => __('X', 'starwishx'),
        'url'   => 'https://twitter.com/intent/tweet?url=' . $encoded_url . '&text=' . $encoded_title,
        /* translators: %s: network name (X). */
        'aria'  => sprintf(__('Share on %s', 'starwishx'), __('X', 'starwishx')),
    ]
];

$allowed_protocols = ['http', 'https', 'viber'];

$panel_id  = function_exists('wp_unique_id')
    ? wp_unique_id('social-share-panel-')
    : 'social-share-panel-' . $post_id;
$status_id = function_exists('wp_unique_id')
    ? wp_unique_id('social-share-status-')
    : 'social-share-status-' . $post_id;

$wrapper_classes = array_filter(['social-share', $wrapper_extra]);
$trigger_classes = array_filter(['social-share__trigger', $trigger_extra]);

$context = wp_json_encode([
    'isOpen'      => false,
    'showStatus'  => false,
    'statusText'  => '',
    'shareUrl'    => $post_url,
    '_timeoutId'  => null,
]);
?>

<div
    class="<?= esc_attr(implode(' ', $wrapper_classes)); ?>"
    data-social-share
    data-wp-interactive="social-share"
    data-wp-context="<?= esc_attr((string) $context); ?>"
    data-wp-on-document--click="actions.handleOutsideClick"
    data-wp-on-document--keydown="actions.handleKeydown">

    <button
        type="button"
        class="<?= esc_attr(implode(' ', $trigger_classes)); ?>"
        data-social-share-trigger
        aria-haspopup="true"
        aria-controls="<?= esc_attr($panel_id); ?>"
        aria-expanded="false"
        data-wp-bind--aria-expanded="context.isOpen"
        data-wp-on--click="actions.toggle">
        <span class="social-share__trigger-label"><?= esc_html($label); ?></span>
        <?php sw_svg_e('icon-share', 18, 20, 'icon-share'); ?>
    </button>

    <div
        id="<?= esc_attr($panel_id); ?>"
        class="social-share__panel"
        data-wp-bind--hidden="!context.isOpen"
        data-wp-class--has-status="context.showStatus"
        hidden>

        <div class="social-share__links" role="group" aria-label="<?= esc_attr($group_label); ?>">

            <button
                type="button"
                class="social-share__action social-share__action--copy"
                aria-label="<?= esc_attr($copy_label); ?>"
                aria-describedby="<?= esc_attr($status_id); ?>"
                data-wp-on--click="actions.copyLink">
                <span class="social-share__icon" aria-hidden="true">
                    <?php sw_svg_e('icon-link', 24, 24); ?>
                </span>
            </button>

            <?php foreach ($networks as $network) : ?>
                <a
                    class="social-share__action"
                    href="<?= esc_url($network['url'], $allowed_protocols); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="<?= esc_attr($network['aria']); ?>"
                    data-wp-on--click="actions.closeOnLink">
                    <span class="social-share__icon" aria-hidden="true">
                        <?php sw_svg_e($network['icon'], 24, 24); ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>

        <span
            id="<?= esc_attr($status_id); ?>"
            class="social-share__status"
            role="status"
            aria-live="polite"
            aria-atomic="true"
            data-wp-class--is-visible="context.showStatus"
            data-wp-text="context.statusText"></span>
    </div>
</div>