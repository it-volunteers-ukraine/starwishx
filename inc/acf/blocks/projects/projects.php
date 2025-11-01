<?php
$default_classes = [
  'projects-section' => 'projects-section',
  'header-block' => 'header-block',
  'sub-title' => 'sub-title',
  'main-title' => 'main-title',
  'projects-wrapper' => 'projects-wrapper',
  'projects-grid' => 'projects-grid',
  'project-card' => 'project-card',
  'img-wrapper' => 'img-wrapper',
  'project-image' => 'project-image',
  'project-category' => 'project-category',
  'project-date' => 'project-date',
  'project-heading' => 'project-heading',
  'arrows' => 'arrows',
  'arrow' => 'arrow',
  'arrow-left' => 'arrow-left',
  'arrow-right' => 'arrow-right',
];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;

if (file_exists($modules_file)) {
  $modules = json_decode(file_get_contents($modules_file), true);
  $classes = array_merge($default_classes, $modules['projects'] ?? []);
}

$cards = get_field('project_cards');
?>



<section class="<?= esc_attr($classes['projects-section']) ?>">
  <div class="<?= esc_attr($classes['header-block']) ?>">
    <span class="<?= esc_attr($classes['sub-title']) ?>">ВІД СЕРЦЯ ДО СЕРЦЯ</span>
    <h3 class="<?= esc_attr($classes['main-title']) ?>">ПРОЄКТИ</h3>
    <div class="<?= esc_attr($classes['arrows']) ?>">
  <svg class="<?= esc_attr($classes['arrow']) ?> <?= esc_attr($classes['arrow-left']) ?>" data-arrow="left">
    <use href="<?= get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-arrow_left"></use>
  </svg>
  <svg class="<?= esc_attr($classes['arrow']) ?> <?= esc_attr($classes['arrow-right']) ?>" data-arrow="right">
    <use href="<?= get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-arrow_right"></use>
  </svg>
</div>
  </div>

  <div class="<?= esc_attr($classes['projects-wrapper']) ?>">
    <?php if ($cards) : ?>
      <div class="<?= esc_attr($classes['projects-grid']) ?>">
        <?php foreach ($cards as $card) :
          $category = $card['category'] ?? '';
          $image_id = $card['image'] ?? false;
          $date = $card['date'] ?? '';
          $link = $card['link'] ?? '#';
          $heading = $card['heading'] ?? '';
          $bg_color = $card['category_bg_color'] ?? '';
          $text_color = $card['category_text_color'] ?? '';
        ?>
          <a href="<?= esc_url($link) ?>" class="<?= esc_attr($classes['project-card']) ?>">
            <?php if ($image_id) : ?>
  <div class="<?= esc_attr($classes['img-wrapper']) ?>">
    <?php
    // Используем категорию как alt, или fallback к пустой строке
    $alt_text = !empty($category) ? esc_attr($category) : '';
    echo wp_get_attachment_image($image_id, 'large', false, [
      'class' => $classes['project-image'],
      'alt'   => $alt_text,
    ]);
    ?>
    <?php if ($category) : ?>
      <span class="<?= esc_attr($classes['project-category']) ?>" style="background-color: <?= esc_attr($bg_color) ?>; color: <?= esc_attr($text_color) ?>;">
        <?= esc_html($category) ?>
      </span>
    <?php endif; ?>
  </div>
<?php endif; ?>
            <?php if ($date) : ?>
              <time class="<?= esc_attr($classes['project-date']) ?>"><?= esc_html($date) ?></time>
            <?php endif; ?>
            <?php if ($heading) : ?>
              <h4 class="<?= esc_attr($classes['project-heading']) ?>"><?= esc_html($heading) ?></h4>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <script src="<?= get_template_directory_uri(); ?>/assets/js/projects.js"></script>
</section>
