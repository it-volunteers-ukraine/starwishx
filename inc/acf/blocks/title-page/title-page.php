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

<section aria-labelledby="<?= esc_attr($classes["title"]); ?>" class="section title-page-section <?= esc_attr($classes["section"]); ?>">
    <header id="<?= esc_attr($classes["title"]); ?>" class="container">
        <h1 class="h3 <?= esc_attr($classes["title"]); ?>">
            <?= esc_html($title); ?>
        </h1>
    </header>
</section>