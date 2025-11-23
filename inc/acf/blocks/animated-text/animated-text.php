<?php
$default_classes = [
  'animated-text-section' => 'animated-text-section',
  'animated-text-content' => 'animated-text-content',
  ];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;

if (file_exists($modules_file)) {
  $modules = json_decode(file_get_contents($modules_file), true);
  $classes = array_merge($default_classes, $modules['animated-text'] ?? []);
}
?>

<section class="section <?php echo esc_attr($classes['animated-text-section']); ?>">
  <div class="container">
    <div class="<?php echo esc_attr($classes['animated-text-content']); ?>">
    <?php echo get_field('text'); ?>
    </div>
  </div>
</section>
