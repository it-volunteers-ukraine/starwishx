<?php
$default_classes = [
  'animated-text-section'  => 'animated-text-section',
  'animated-text-wrapper'  => 'animated-text-wrapper',
  'animated-text-content'  => 'animated-text-content',
];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;

if (file_exists($modules_file)) {
  $modules = json_decode(file_get_contents($modules_file), true);
  $classes = array_merge($default_classes, $modules['animated-text'] ?? []);
}

$text = get_field('animated_text');
?>

<?php if ($text) { ?>
<section class="<?php echo esc_attr($classes['animated-text-section']); ?>">
  <div class="container">
    <div class="<?php echo esc_attr($classes['animated-text-wrapper']); ?>">
      <div class="<?php echo esc_attr($classes['animated-text-content']); ?>">
        <p><?php echo get_field('animated_text'); ?></p>
      </div>
    </div>
  </div>
</section>
<?php } ?>
