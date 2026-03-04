<?php
$default_classes = [
  'projects-section' => 'projects-section',
  'header-block' => 'header-block',
  'suptitle' => 'suptitle',
  'title' => 'title',
  'projects-wrapper' => 'projects-wrapper',
  'projects-swiper' => 'projects-swiper',
  'projects-swiper-wrapper' => 'projects-swiper-wrapper',
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
  // apply escaping same time:
  $classes = array_map('esc_attr', array_merge($default_classes, $modules['projects'] ?? []));
}

$title = wp_kses_post(get_field('title'));
$suptitle = wp_kses_post(get_field('suptitle'));
$slides_count = esc_attr(get_field('slides_count')) ?? 8;

$query_args = apply_filters('projects_block_query_args', [
  'post_type'      => 'project',
  'posts_per_page' => $slides_count,
  'post_status'    => 'publish',
  'orderby'        => 'date',
  'order'          => 'DESC',
]);
$query = new WP_Query($query_args);

?>

<section class="<?= $classes['projects-section'] ?>">
  <div class="<?= $classes['header-block'] ?>">
    <span class="<?= $classes['suptitle'] ?>"><?php echo $suptitle ?></span>
    <h3 class="<?= $classes['title'] ?>"><?php echo $title ?></h3>
    <div class="<?= $classes['arrows'] ?>">
      <!-- Swiper navigation buttons -->
      <button type="button" class="arrow-left <?= $classes['arrow'] ?> <?= $classes['arrow-left'] ?>" aria-label="Previous projects">
        <svg width="40" height="40" focusable="false" tabindex="-1" aria-hidden="true">
          <use href="<?= get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-arrow_left"></use>
        </svg>
      </button>
      <button type="button" class="arrow-right <?= $classes['arrow'] ?> <?= $classes['arrow-right'] ?>" aria-label="Next projects">
        <svg width="40" height="40" focusable="false" tabindex="-1" aria-hidden="true">
          <use href="<?= get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-arrow_right"></use>
        </svg>
      </button>
    </div>
  </div>

  <div class="<?= $classes['projects-wrapper'] ?>">
    <?php if ($query->have_posts()) : ?>
      <!-- Swiper -->
      <div class="swiper projects-swiper <?= $classes['projects-swiper'] ?>">
        <div class="swiper-wrapper projects-swiper-wrapper <?= $classes['projects-swiper-wrapper'] ?>">
          <?php while ($query->have_posts()) : $query->the_post();

            $post_id = get_the_ID();
            $image_id = get_post_thumbnail_id($post_id);
            $date_display = get_the_date('d.m.Y', $post_id);
            $permalink = get_permalink($post_id);
            $title_post = get_the_title($post_id);
            $category = get_field('category', $post_id);
          ?>

            <div class="swiper-slide">
              <a href="<?php echo esc_url($permalink); ?>" class="<?= $classes['project-card'] ?>">

                <?php if ($image_id) : ?>
                  <div class="<?= $classes['img-wrapper'] ?>">
                    <?php
                    echo wp_get_attachment_image(
                      $image_id,
                      'large',
                      false,
                      ['class' => $classes['project-image']]
                    );
                    ?>

                    <?php if ($category) : ?>
                      <span class="<?= $classes['project-category'] ?>"
                        style="background-color: <?php echo esc_attr($bg_color); ?>;
                               color: <?php echo esc_attr($text_color); ?>;">
                        <?php echo esc_html($category); ?>
                      </span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <time class="<?= $classes['project-date'] ?>">
                  <?php echo esc_html($date_display); ?>
                </time>

                <h4 class="<?= $classes['project-heading'] ?>">
                  <?php echo esc_html($title_post); ?>
                </h4>

              </a>
            </div>

          <?php endwhile; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>
</section>

<?php wp_reset_postdata(); ?>

<?php
wp_enqueue_script('swiper-js');
wp_enqueue_script(
  'projects-block-script',
  get_template_directory_uri() . '/assets/js/projects.js',
  ['swiper-js'],
  '1.0',
  true
);
?>