<?php

$default_classes = [
    'section' => 'section',
    'container' => 'container',
    'title-wrap' => 'title-wrap',
    'title' => 'title',
    'subtitle' => 'subtitle',
    'list' => 'list',
    'item' => 'item',
    'content' => 'content',
    'item-title' => 'item-title',
    'item-label' => 'item-label',
    'block-photo' => 'block-photo',
    'item-date' => 'item-date',
    'photo-wrap' => 'photo-wrap',
    'photo-img' => 'photo-img',
    'text' => 'text',
    'icon' => 'icon',
    'btn1' => 'btn1',
    'btn2' => 'btn2',
];

$taxonomy     = 'category-oportunities';
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes      = $default_classes;

// Block configuration fields (ACF — block-level settings, not post data)
$title      = get_field('title');
$label_text = get_field('label_text');
$btn_text   = get_field('btn_text');
$btn_url    = get_field('btn_page');

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['news'] ?? []);
}

// ── Category color CSS (classes keyed by term slug) ─────────────────────────
$css = sw_get_taxonomy_top_level_colors_styles($taxonomy);
if (!empty($css)) {
    wp_register_style('cat-oportunities-color-styles', false);
    wp_enqueue_style('cat-oportunities-color-styles');
    wp_add_inline_style('cat-oportunities-color-styles', $css);
}

// ── Data layer ──────────────────────────────────────────────────────────────

// Only top-level categories with posts
$terms = get_terms([
    'taxonomy'   => $taxonomy,
    'parent'     => 0,
    'hide_empty' => true,
]);

if (is_wp_error($terms)) {
    $terms = [];
}

// Latest post per category
$items = [];
foreach ($terms as $term) {
    $query = new WP_Query([
        'post_type'      => 'news',
        'posts_per_page' => 1,
        'no_found_rows'  => true,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'tax_query'      => [[
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => $term->term_id,
        ]],
    ]);

    if ($query->have_posts()) {
        $p = $query->posts[0];
        $p->term_name = $term->name;
        $p->term_slug = $term->slug;
        $items[] = $p;
    }

    wp_reset_postdata();
}
?>

<section aria-labelledby="<?= esc_attr($classes['title']); ?>" class="section <?= esc_attr($classes['section']); ?>">
    <div class="container">
        <?php if ($items) : ?>
            <header>
                <div class="<?= esc_attr($classes['subtitle']); ?>"><?= esc_html($label_text); ?></div>
                <div class="<?= esc_attr($classes['title-wrap']); ?>">
                    <h2 id="<?= esc_attr($classes['title']); ?>" class="h2-big <?= esc_attr($classes['title']); ?>"><?= esc_html($title); ?></h2>
                    <a href="<?= esc_url($btn_url); ?>" class="btn <?= esc_attr($classes['btn1']); ?>"><?= esc_html($btn_text); ?></a>
                </div>
            </header>

            <div class="<?= esc_attr($classes['list']); ?>" role="list">
                <?php foreach ($items as $item) : ?>
                    <?php
                    $post_id    = $item->ID;
                    $permalink  = get_permalink($post_id);
                    $item_title = get_the_title($post_id);
                    $item_date  = get_the_date('d.m.Y', $item);
                    $item_iso   = get_the_date('Y-m-d', $item);
                    $item_label = $item->term_name;
                    $term_slug  = $item->term_slug;

                    if (has_post_thumbnail($post_id)) {
                        $photo_url = get_the_post_thumbnail_url($post_id, 'large');
                        $photo_alt = get_the_post_thumbnail_caption($post_id) ?: $item_title;
                    } else {
                        $photo_url = get_template_directory_uri() . '/assets/img/card-placeholder.png';
                        $photo_alt = $item_title;
                    }
                    ?>
                    <article class="<?= esc_attr($classes['item']); ?>">
                        <a href="<?= esc_url($permalink); ?>" class="<?= esc_attr($classes['content']); ?>"
                            aria-label="<?= esc_attr($item_title); ?>" rel="bookmark">
                            <figure class="<?= esc_attr($classes['photo-wrap']); ?>">
                                <img src="<?= esc_url($photo_url); ?>"
                                    class="<?= esc_attr($classes['photo-img']); ?>"
                                    alt="<?= esc_attr($photo_alt); ?>"
                                    loading="lazy">
                                <figcaption class="<?= esc_attr($classes['item-label']); ?> <?= esc_attr($term_slug); ?>">
                                    <?= esc_html($item_label); ?>
                                </figcaption>
                            </figure>
                            <time class="text-small <?= esc_attr($classes['item-date']); ?>"
                                datetime="<?= esc_attr($item_iso); ?>">
                                <?= esc_html($item_date); ?>
                            </time>
                            <?php if ($item_title) : ?>
                                <header>
                                    <h3 class="btn-text-medium <?= esc_attr($classes['item-title']); ?>">
                                        <?= esc_html($item_title); ?>
                                    </h3>
                                </header>
                            <?php endif; ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
            <a href="<?= esc_url($btn_url); ?>" class="btn <?= esc_attr($classes['btn2']); ?>"><?= esc_html($btn_text); ?></a>
        <?php endif; ?>
    </div>
</section>