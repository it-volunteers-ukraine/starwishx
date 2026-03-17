<?php
// Loading classes
$default_classes = [
    'section' => 'section',
    'container' => 'container',
    'hero' => 'hero',
    'link' => 'link',
    'image-wrap' => 'image-wrap',
    'image' => 'image',
    'title' => 'title',
    'subtitle' => 'subtitle',
    'wrap-link' => 'wrap-link',
    'text-block' => 'text-block',
    'text-bottom' => 'text-bottom',


];

$classes = $default_classes;
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['hero'] ?? []);
}


// Home page
// $home_id = get_option('page_on_front');
// $home_url = get_the_permalink($home_id);
// $home_title = get_the_title($home_id);

// Active item
// $active_title = get_the_title();
$title = esc_html(get_field('title'));
$subtitle = esc_html(get_field('subtitle'));
$give = esc_html(get_field('give'));
$get = esc_html(get_field('get'));
$text_bottom = esc_html(get_field('text_bottom'));
$image = get_field('image');
?>

<section class="section <?= esc_attr($classes["section"]); ?>" style="--hero-bg: url('<?= esc_url($image['url']); ?>');">
    <!-- <div class="< ?php echo esc_attr($classes['image-wrap']); ?>">
        <img class="< ?php echo esc_attr($classes['image']); ?>" src="< ?php echo esc_url($image['url']); ?>" alt="Hero Image">
    </div> -->
    <div class="container <?= esc_attr($classes['container']); ?>">
        <div class="<?= esc_attr($classes['hero']); ?>">
            <h1 class="h1 <?= esc_attr($classes['title']); ?>"><?= $title; ?></h1>
            <p class="subtitle-text-r <?= esc_attr($classes['subtitle']); ?>"><?= $subtitle; ?></p>
            <div class="<?= esc_attr($classes['wrap-link']); ?>">
                <a class="h4" href="/opportunities/"><?= $give; ?></a>
                <a class="h4" href="/launchpad/?panel=opportunities&view=add"><?= $get; ?></a>
            </div>
            <p class="text-r <?= esc_attr($classes['text-bottom']); ?>"><?= $text_bottom; ?></p>
        </div>
    </div>
</section>