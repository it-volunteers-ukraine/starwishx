<?php
$default_classes = [
  'animated-text-section' => 'animated-text-section',
  'animated-text-wrapper' => 'animated-text-wrapper',
  'animated-text-content' => 'animated-text-content',
];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;

if (file_exists($modules_file)) {
  $modules = json_decode(file_get_contents($modules_file), true);
  $classes = array_merge($default_classes, $modules['animated-text'] ?? []);
}

$text = get_field('animated_text');
?>

<section class="<?= esc_attr($classes['animated-text-section']) ?>">
  <div class="<?= esc_attr($classes['animated-text-wrapper']) ?>">
    <?php if ($text) : ?>
      <div class="<?= esc_attr($classes['animated-text-content']) ?>">
        <?= esc_html($text) ?>
      </div>
    <?php endif; ?>
  </div>
</section>
