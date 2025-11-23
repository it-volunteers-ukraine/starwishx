<?php
// Loading classes
$default_classes = [
    'section' => 'section',
    'list' => 'list',
    'item' => 'item',
    'selected' => 'selected',
    'link' => 'link'
];

echo 'dasdasda';
$ur = get_permalink();
print_r('wp_reques: ', get_permalink());

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['breadcrumbs'] ?? []);
}

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
                        <?php echo esc_html($home_title); ?>
                    </a>
                </li>
                <li class="link-bc-selected <?php echo esc_attr($classes["item"]); ?>">
                    <span>
                        <?php echo esc_html($active_title); ?>
                    </span>
                </li>
            </ul>
        </nav>
    </div>
</section>