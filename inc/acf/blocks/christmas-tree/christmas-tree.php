<?php
// Loading classes
$default_classes = [
    'image' => 'image',


];

$img = esc_url(get_field('image'));
$end_date = esc_html(get_field('end_date'));

$today = current_time('Ymd');

$classes = $default_classes;
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['christmas-tree'] ?? []);
}


// Active item
$active_title = get_the_title();
?>

<!-- <section class="section <?php echo esc_attr($classes["section"]); ?>"> -->
<!-- <div class="container"> -->
<?php if ($end_date > $today) : ?>
    <img class="<?php echo esc_attr($classes['image']); ?>" src="<?php echo $img; ?>" border="0" alt="rozhdestvenskaya-elka-animatsionnaya-kartinka-0335" />
<?php endif; ?>
<!-- </div> -->
<!-- </section> -->