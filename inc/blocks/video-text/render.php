<?php

/**
 * Block: starwishx/video-text — server render (front end and editor preview).
 *
 * Section with a title, a click-to-play video and a text column. With a cover
 * image the video is a facade — a real <button> showing the cover and a play
 * icon; build/view.js swaps it for the autoplaying iframe on click, so no
 * YouTube/Vimeo bytes load until the visitor asks for them. Without a cover a
 * lazy iframe is rendered directly.
 *
 * Must output exactly ONE root element and never an empty string: the editor
 * (ServerSideRender) merges the block wrapper props into the first root tag.
 * No function declarations here — WordPress `require`s this file once per
 * block instance; helpers live in Blocks\Support\VideoEmbed.
 *
 * @var array<string, mixed> $attributes Block attributes (defaults applied by WP_Block).
 * @var string               $content    Unused (dynamic block).
 * @var WP_Block             $block      Block instance.
 *
 * File: inc/blocks/video-text/render.php
 */

declare(strict_types=1);

use Blocks\Support\VideoEmbed;

$title     = trim((string) ($attributes['title'] ?? ''));
$video_url = trim((string) ($attributes['videoUrl'] ?? ''));
$text      = trim((string) ($attributes['text'] ?? ''));
$cover_id  = (int) ($attributes['coverId'] ?? 0);

$embed_url = VideoEmbed::fromInput($video_url);

// '' when the attachment was deleted or is not an image → plain iframe below.
// alt="" on purpose: the facade <button> already carries the accessible name.
$cover_html = $cover_id > 0
    ? wp_get_attachment_image($cover_id, 'large', false, ['class' => 'video-text__cover', 'alt' => ''])
    : '';

$player_title = $title !== '' ? $title : __('Video', 'starwishx');
$play_label   = $title !== ''
    /* translators: %s: video title */
    ? sprintf(__('Play video: %s', 'starwishx'), $title)
    : __('Play video', 'starwishx');

$media_html = '';
if ($embed_url !== null && $cover_html !== '') {
    $media_html = sprintf(
        '<button type="button" class="video-text__facade" aria-label="%1$s" data-embed-url="%2$s" data-title="%3$s">%4$s%5$s</button>',
        esc_attr($play_label),
        esc_url(VideoEmbed::withAutoplay($embed_url)),
        esc_attr($player_title),
        $cover_html, // escaped by wp_get_attachment_image()
        sw_svg('icon-youtube', 68, 48, 'video-text__play') // kses-escaped, aria-hidden
    );
} elseif ($embed_url !== null) {
    $media_html = sprintf(
        '<iframe class="video-text__player" src="%1$s" title="%2$s" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>',
        esc_url($embed_url),
        esc_attr($player_title)
    );
} elseif ($video_url !== '') {
    // Unknown provider: WP_Embed caches per post (_oembed_* meta); wp_oembed_get() would fetch on every render.
    $media_html = (string) $GLOBALS['wp_embed']->shortcode([], $video_url);
}

// Unique per instance so two blocks on a page never share an id; only emitted
// with a heading, so aria-labelledby never dangles.
$title_id = $title !== '' ? wp_unique_id('video-text-title-') : '';

$wrapper_args = ['class' => 'section video-text'];
if ($title_id !== '') {
    $wrapper_args['aria-labelledby'] = $title_id;
}
$wrapper = get_block_wrapper_attributes($wrapper_args);

$img_base = get_template_directory_uri() . '/assets/img';
?>
<section <?php echo $wrapper; // escaped by get_block_wrapper_attributes() ?>>
    <div class="container video-text__container">
        <?php if ($title !== '') : ?>
            <h2 id="<?php echo esc_attr($title_id); ?>" class="h3 video-text__title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        <div class="video-text__content">
            <?php if ($media_html !== '') : ?>
                <div class="video-text__media"><?php echo $media_html; // escaped above ?></div>
            <?php endif; ?>
            <div class="video-text__body">
                <?php if ($text !== '') : ?>
                    <p class="video-text__text"><?php echo esc_html($text); ?></p>
                <?php endif; ?>
                <div class="video-text__icon" aria-hidden="true">
                    <img class="video-text__icon-bg" src="<?php echo esc_url($img_base . '/planet-bg-radial-gradient.svg'); ?>" alt="" width="96" height="96">
                    <img class="video-text__icon-mask" src="<?php echo esc_url($img_base . '/planet-mask-gradient.svg'); ?>" alt="" width="96" height="96">
                </div>
            </div>
        </div>
    </div>
</section>
