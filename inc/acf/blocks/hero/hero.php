<?php
// Loading classes
$default_classes = [
    'section' => 'section',
    'container' => 'container',
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

<section class="section <?php echo esc_attr($classes["section"]); ?>" style="--hero-bg: url('<?php echo esc_url($image['url']); ?>');">
    <!-- <div class="<?php echo esc_attr($classes['image-wrap']); ?>">
        <img class="<?php echo esc_attr($classes['image']); ?>" src="<?php echo esc_url($image['url']); ?>" alt="Hero Image">
    </div> -->

    <div class="container <?php echo esc_attr($classes['container']); ?>">
        <div class="<?php echo esc_attr($classes['text-block']); ?>">
            <h1 class="h1 <?php echo esc_attr($classes['title']); ?>"><?php echo $title; ?></h1>
            <p class="<?php echo esc_attr($classes['subtitle']); ?>"><?php echo $subtitle; ?></p>
            <div class="<?php echo esc_attr($classes['wrap-link']); ?>">
                <a class="h4" href="#"><?php echo $give; ?></a>
                <a class="h4" href="#"><?php echo $get; ?></a>
            </div>
            <p class="text-r <?php echo esc_attr($classes['text-bottom']); ?>"><?php echo $text_bottom; ?></p>

        </div>
    </div>
</section>