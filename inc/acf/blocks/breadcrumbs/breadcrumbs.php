<?php
// Loading classes
$default_classes = [
    'section' => 'section',
    'list' => 'list',
    'item' => 'item',
    'selected' => 'selected',
    'link' => 'link',
    'arrow-icon' => 'arrow-icon',
    'home-icon' => 'home-icon'

];

$classes = $default_classes;
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['breadcrumbs'] ?? []);
}

global $post;
$breadcrumbs = [];

while ($post) {
    $breadcrumbs[] = [
        'title' => get_the_title($post),
        'link'  => get_permalink($post),
    ];

    if ($post->post_parent) {
        $post = get_post($post->post_parent);
    } else {
        $post = false;
    }
}
$breadcrumbs = array_reverse($breadcrumbs);

// Home page
$home_id = get_option('page_on_front');
$home_url = get_the_permalink($home_id);
$home_title = get_the_title($home_id);

// Active item
$active_title = get_the_title();
?>

<section class="section <?php echo esc_attr($classes["section"]); ?>">
    <div class="container">
        <nav>
            <ul class="text-r <?php echo esc_attr($classes["list"]); ?>">
                <li class="<?php echo esc_attr($classes["item"]); ?>">
                    <a class="link-bc" href="<?php echo esc_url($home_url); ?>">
                        <svg class="<?php echo esc_attr($classes["home-icon"]); ?>">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon_house"></use>
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