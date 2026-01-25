<?php
// Loading classes
$default_classes = [
    'section' => 'section',
    'title-page-section' => 'title-page-section',
    'title' => 'title',
];

$classes = $default_classes;
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['title-pages'] ?? []);
}

$title = get_the_title();
?>

<section class="section title-page-section <?php echo esc_attr($classes["section"]); ?>">
    <div class="container">
        <div class="<?php echo esc_attr($classes["title"]); ?>">
            <?php echo esc_html($title); ?>
        </div>
    </div>
</section>