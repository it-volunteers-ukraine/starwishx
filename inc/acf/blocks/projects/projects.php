<?php
/**
 * Block: Projects (random posts)
 */

$default_classes = [
    'section'      => 'section',
    'container'    => 'container',
    'title'        => 'title',
    'grid'         => 'grid',
    'card'         => 'card',
    'card-img'     => 'card-img',
    'card-title'   => 'card-title',
    'card-excerpt' => 'card-excerpt',
    'btn-wrap'     => 'btn-wrap',
    'btn'          => 'btn',
];

// мержим с темой (аналогично photo-text)
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['projects'] ?? []);
}

// поля ACF
$section_title = get_field('section_title') ?: __('Проєкти', '_themedomain');
$posts_count   = get_field('posts_count') ?: 6;
$show_btn      = get_field('show_all_button');
$btn_text      = get_field('button_text')   ?: __('Всі проєкти', '_themedomain');
$btn_link      = get_field('button_link');

// запрос: только опубликованные, в случайном порядке
$args = [
    'post_type'      => 'project',   // замените на свой CPT или 'post'
    'posts_per_page' => $posts_count,
    'orderby'        => 'rand',
    'post_status'    => 'publish',
];
$projects = new WP_Query($args);
?>

<section class="<?php echo esc_attr($classes['section']); ?>">
  <div class="<?php echo esc_attr($classes['container']); ?>">
    <h2 class="<?php echo esc_attr($classes['title']); ?>">
      <?php echo esc_html($section_title); ?>
    </h2>

    <?php if ($projects->have_posts()) : ?>
      <div class="<?php echo esc_attr($classes['grid']); ?>">
        <?php while ($projects->have_posts()) : $projects->the_post(); ?>
          <article class="<?php echo esc_attr($classes['card']); ?>">
            <a href="<?php the_permalink(); ?>" aria-label="<?php the_title_attribute(); ?>">
              <?php if (has_post_thumbnail()) : ?>
                <div class="<?php echo esc_attr($classes['card-img']); ?>">
                  <?php the_post_thumbnail('medium'); ?>
                </div>
              <?php endif; ?>

              <h3 class="<?php echo esc_attr($classes['card-title']); ?>">
                <?php the_title(); ?>
              </h3>

              <?php if (has_excerpt()) : ?>
                <p class="<?php echo esc_attr($classes['card-excerpt']); ?>">
                  <?php echo wp_trim_words(get_the_excerpt(), 12); ?>
                </p>
              <?php endif; ?>
            </a>
          </article>
        <?php endwhile; ?>
        <?php wp_reset_postdata(); ?>
      </div>
    <?php endif; ?>

    <?php if ($show_btn && $btn_link) : ?>
      <div class="<?php echo esc_attr($classes['btn-wrap']); ?>">
        <a href="<?php echo esc_url($btn_link); ?>" class="<?php echo esc_attr($classes['btn']); ?>">
          <?php echo esc_html($btn_text); ?>
        </a>
      </div>
    <?php endif; ?>
  </div>
</section>