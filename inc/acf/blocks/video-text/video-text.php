<?php

/**
 * ACF Block: Video & Text
 *
 * Renders a section with a title, video, and text with a decorative icon.
 * Uses a facade pattern for video: shows a cover image + play button,
 * loads the iframe only on click for better page performance.
 *
 * File: inc/acf/blocks/video-text/video-text.php
 */

$default_classes = [
    'container'     => 'container',
    'title'         => 'title',
    'content'       => 'content',
    'content-video' => 'content-video',
    'content-text'  => 'content-text',
    'text'          => 'text',
    'figure-icon'   => 'figure-icon',
    'icon-bg'       => 'icon-bg',
    'icon-mask'     => 'icon-mask',
    'video-facade'  => 'video-facade',
    'video-play'    => 'video-play',
    'video-cover'   => 'video-cover',
];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['video-text'] ?? []);
}

$title       = esc_html(get_field('title'));
$video_embed = get_field('video');
$text        = esc_html(get_field('text'));
$cover_id    = get_field('video_cover');

/* ---- Parse oEmbed URL → embed URL ---- */
$embed_url = '';
if ($video_embed && $cover_id) {
    // If it's already an iframe, extract the src URL 
    //? (Not sure we need this check IRL)
    if (preg_match('/src=["\']([^"\']+)["\']/', $video_embed, $iframe_match)) {
        $url = $iframe_match[1];
        // Remove query params like ?feature=oembed to get clean URL
        $url = str_replace(['?feature=oembed', '&feature=oembed'], '', $url);
    } else {
        // Otherwise use the plain URL
        $url = trim($video_embed);
    }

    // YouTube: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/shorts/ID, youtube.com/embed/ID
    if (preg_match('#(?:youtube\.com/watch\?.*v=|youtu\.be/|youtube\.com/shorts/|youtube\.com/embed/)([a-zA-Z0-9_-]{11})#', $url, $m)) {
        $embed_url = 'https://www.youtube.com/embed/' . $m[1] . '?autoplay=1';
    }
    // Vimeo: vimeo.com/ID, vimeo.com/video/ID, player.vimeo.com/video/ID
    elseif (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
        $embed_url = 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1';
    }
}


$use_facade = $embed_url && $cover_id;
$sprite_path = get_template_directory_uri() . '/assets/img/sprites.svg';
?>
<section aria-labelledby="<?= esc_attr($classes['title']); ?>" class="section">
    <div class="container <?= esc_attr($classes['container']); ?>">
        <?php if ($title) : ?>
            <h2 id="<?= esc_attr($classes['title']); ?>" class="h3 <?= esc_attr($classes['title']); ?>"><?= $title; ?></h2>
        <?php endif; ?>
        <div class="<?= esc_attr($classes['content']); ?>">
            <?php if ($video_embed) : ?>
                <div class="<?= esc_attr($classes['content-video']); ?>">
                    <?php if ($use_facade) : ?>

                        <div class="<?= esc_attr($classes['video-facade']); ?>"
                            role="button"
                            tabindex="0"
                            aria-label="<?= esc_attr__('Play video', 'starwishx') ?>"
                            data-embed-url="<?= esc_url($embed_url) ?>">
                            <?= wp_get_attachment_image($cover_id, 'large', false, [
                                'class' => esc_attr($classes['video-cover']),
                                'alt'   => esc_attr($title ?: __('Video cover', 'starwishx')),
                            ]) ?>
                            <svg class="<?= esc_attr($classes['video-play']); ?>" aria-hidden="true">
                                <use href="<?= esc_url($sprite_path) ?>#icon-youtube"></use>
                            </svg>
                        </div>
                    <?php else : ?>
                        <?php remove_filter('the_content', 'wpautop'); ?>
                        <?= apply_filters('the_content', $video_embed); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="<?= esc_attr($classes['content-text']); ?>">

                <?php if ($text) : ?>
                    <p class="<?= esc_attr($classes['text']); ?>">
                        <?= esc_html($text); ?>
                    </p>
                <?php endif; ?>
                <figure class="<?= esc_attr($classes['figure-icon']); ?>">
                    <img src="<?= get_template_directory_uri(); ?>/assets/img/planet-bg-radial-gradient.svg" class="<?= esc_attr($classes['icon-bg']); ?>" alt="Icon star">
                    <img src="<?= get_template_directory_uri(); ?>/assets/img/planet-mask-gradient.svg" class="<?= esc_attr($classes['icon-mask']); ?>" alt="Mask for icon star">
                </figure>
            </div>
        </div>
    </div>
</section>