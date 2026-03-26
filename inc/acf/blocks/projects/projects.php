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

<section aria-labelledby="<?= $classes['title'] ?>" class="<?= $classes['projects-section'] ?>">
    <header class="<?= $classes['header-block'] ?>">
        <span class="<?= $classes['suptitle'] ?>"><?php echo $suptitle ?></span>
        <h2 id="<?= $classes['title'] ?>" class="<?= $classes['title'] ?>"><?php echo $title ?></h2>
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
    </header>

    <div class="<?= $classes['projects-wrapper'] ?>">
        <?php if ($query->have_posts()) : ?>
            <!-- Swiper -->
            <div class="swiper projects-swiper <?= $classes['projects-swiper'] ?>">
                <div class="swiper-wrapper <?= $classes['projects-swiper-wrapper'] ?>" role="list">
                    <?php while ($query->have_posts()) : $query->the_post();

                        $post_id      = get_the_ID();
                        $image_id     = get_post_thumbnail_id($post_id);
                        $iso_date     = get_the_date('c', $post_id);
                        $date_display = get_the_date('d.m.Y', $post_id);
                        $permalink    = get_permalink($post_id);
                        $title_post   = get_the_title($post_id);
                        // $category = get_field('category', $post_id); // possible feature
                    ?>

                        <article class="swiper-slide">
                            <a href="<?= esc_url($permalink); ?>" class="<?= $classes['project-card'] ?>" rel="bookmark">

                                <?php if ($image_id) : ?>
                                    <figure class="<?= $classes['img-wrapper'] ?>">
                                        <?php
                                        echo wp_get_attachment_image(
                                            $image_id,
                                            'large',
                                            false,
                                            ['class' => $classes['project-image']]
                                        );
                                        ?>
                                    </figure>
                                <?php endif; ?>

                                <time datetime="<?= esc_attr($iso_date); ?>" class="<?= $classes['project-date'] ?>">
                                    <?php echo esc_html($date_display); ?>
                                </time>
                                <header>
                                    <h3 class="<?= $classes['project-heading'] ?>">
                                        <?php echo esc_html($title_post); ?>
                                    </h3>
                                </header>

                            </a>
                        </article>

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