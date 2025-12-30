<?php
// Loading classes
$default_classes = [
    'section' => 'section',
    'link' => 'link',
    'image' => 'image',

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
$image = get_field('image');
?>

<section class="section <?php echo esc_attr($classes["section"]); ?>">
    <div class="<?php echo esc_attr($classes['image-wrap']); ?>">
        <img class="<?php echo esc_attr($classes['image']); ?>" src="<?php echo esc_url($image['url']); ?>" alt="Hero Image">

    </div>
    <div class="container">
    </div>
</section>