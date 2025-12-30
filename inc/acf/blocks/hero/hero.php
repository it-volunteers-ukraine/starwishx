<?php
// Loading classes
$default_classes = [
    'section' => 'section',
    'link' => 'link',

];

$classes = $default_classes;
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['hero'] ?? []);
}


// Home page
$home_id = get_option('page_on_front');
$home_url = get_the_permalink($home_id);
$home_title = get_the_title($home_id);

// Active item
$active_title = get_the_title();
$title= esc_html(get_field('title'));
$subtitle= esc_html(get_field('subtitle'));
$give=esc_html(get_field('give'));
$get=esc_html(get_field('get'));
$imaage=get_field('image');
print_r($imaage);
?>

<section class="section <?php echo esc_attr($classes["section"]); ?>">
    <div class="container">
        <img class="" src="<?php echo get_template_directory_uri(); ?>/assets/img/hero/hero-image.jpg" alt="Hero Image">
        <nav>
            <ul class="text-r <?php echo esc_attr($classes["list"]); ?>">
                <li class="<?php echo esc_attr($classes["item"]); ?>">
                    <a class="link-bc" href="<?php echo esc_url($home_url); ?>">
                        <svg class="<?php echo esc_attr($classes["home-icon"]); ?>">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-house"></use>
                        </svg>
                    </a>
                </li>
                <?php foreach ($breadcrumbs as $crumb): ?>
                    <li class="<?php echo esc_attr($classes["item"]); ?>">
                        <svg class="<?php echo esc_attr($classes["arrow-icon"]); ?>">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-arrow"></use>
                        </svg>
                        <a class="link-bc" href="<?php echo esc_url($crumb['link']); ?>">
                            <?php echo esc_html($crumb['title']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
</section>