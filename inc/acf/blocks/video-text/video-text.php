<?php
$default_classes = [
    'container' => 'container',
    'title' => 'title',
    'content' => 'content',
    'content-video' => 'content-video',
    'content-text' => 'content-text',
    'text' => 'text',
    'figure-icon' => 'figure-icon',
    'icon-bg' => 'icon-bg',
    'icon-mask' => 'icon-mask',
    'contacts' => 'contacts',
];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
$title = esc_html(get_field('title'));
$video_embed = get_field('video');
$text = esc_html(get_field('text'));

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['video-text'] ?? []);
}
?>

<section aria-labelledby="<?= esc_attr($classes['title']); ?>" class="section ">
    <div class="container <?= esc_attr($classes['container']); ?>">
        <?php if ($title) : ?>
            <h2 id="<?= esc_attr($classes['title']); ?>" class="h3 <?= esc_attr($classes['title']); ?>"><?= $title; ?></h2>
        <?php endif; ?>
        <div class="<?= esc_attr($classes['content']); ?>">
            <?php if ($video_embed) : ?>
                <div class="<?= esc_attr($classes['content-video']); ?>">
                    <?php remove_filter('the_content', 'wpautop'); ?>
                    <?= apply_filters('the_content', $video_embed); ?>
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