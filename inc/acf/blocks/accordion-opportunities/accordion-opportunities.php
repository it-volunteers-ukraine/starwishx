<?php
$default_classes = [
    'section'     => 'section',
    'active'      => 'active',
    'container'   => 'container',
    'suptitle'    => 'suptitle',
    'header'      => 'header',
    'header-wrap' => 'header-wrap',
    'list'        => 'list',
    'item'        => 'item',
    'content'     => 'content',
    'block-title' => 'block-title',
    'item-title'  => 'item-title',
    'count'       => 'count',
    'block-photo' => 'block-photo',
    'photo'       => 'photo',
    'photo-img'   => 'photo-img',
    'description' => 'description',
    'icon'        => 'icon',
    'btn1'        => 'btn1',
    'btn2'        => 'btn2'
];

$base_url_opportunities  = 'opportunities/';
$is_mode_click_for_touch = get_field('mode_click_for_touch');

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes      = $default_classes;
$header       = get_field('header');
$items        = get_field('accordion') ?: [];
$suptitle     = get_field('suptitle');
$btn_text     = get_field('btn_text');
$btn_url      = get_field('btn_page');

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['accordion-opportunities'] ?? []);
}

$taxonomy = 'category-oportunities';
// ── Category color CSS (classes keyed by term slug) ─────────────────────────
$css = sw_get_taxonomy_top_level_colors_styles($taxonomy);
if (!empty($css)) {
    wp_register_style('cat-oportunities-color-styles', false);
    wp_enqueue_style('cat-oportunities-color-styles', false);
    wp_add_inline_style('cat-oportunities-color-styles', $css);
}

// Batch-fetch taxonomy terms — one query instead of N get_term() calls in the loop
$terms_map = [];
if ($items) {
    $term_ids = array_filter(array_map(function ($item) {
        return (int) ($item['accordion_opportunity_category'] ?? 0);
    }, $items));

    if ($term_ids) {
        foreach (sw_get_prepared_terms($term_ids, $taxonomy) as $term) {
            $terms_map[$term->term_id] = $term;
        }
    }
}
?>

<section aria-labelledby="<?= esc_attr($classes['header']); ?>" class="section <?= esc_attr($classes['section']); ?> ">
    <div class="container ">
        <?php if ($items) : ?>
            <div class="<?= esc_attr($classes['suptitle']); ?>"><?= esc_html($suptitle); ?></div>
            <header id="<?= esc_attr($classes['header']); ?>" class="<?= esc_attr($classes['header-wrap']); ?>">
                <h2 class="h2-big header <?= esc_attr($classes['header']); ?>"><?= esc_html($header); ?></h2>
                <a href="<?= esc_url($btn_url) ?>" class="btn <?= esc_attr($classes['btn1']); ?>"><?= esc_html($btn_text); ?></a>
            </header>

            <ol id="accordion-opportunities" data-click-mode="<?= $is_mode_click_for_touch ? 'true' : 'false'; ?>" class="<?= esc_attr($classes['list']); ?>">
                <?php $count = 0; ?>
                <?php foreach ($items as $item) : ?>
                    <?php
                    $count++;
                    $term_id     = (int) ($item['accordion_opportunity_category'] ?? 0);
                    $term        = $terms_map[$term_id] ?? null;
                    $item_title  = $term ? $term->name : '';
                    $item_slug   = $term ? $term->slug : '';
                    $description = trim($item['description'] ?? '');
                    $photo       = $item['photo'] ?? null;
                    $photo_url   = $photo['sizes']['large'] ?? '';
                    $photo_alt   = ($photo['alt'] ?? '') ?: ($photo['title'] ?? '');
                    ?>
                    <li class="<?= esc_attr($classes['item']); ?>">
                        <?php if ($item_title) : ?>
                            <div class="<?= esc_attr($classes['block-title']); ?>">
                                <div class="<?= esc_attr($classes['count']); ?>"><?= sprintf("%02d", $count); ?></div>
                                <!-- < ?= esc_attr($item_slug); ?>  -->
                                <h3 class="h5 <?= esc_attr($classes['item-title']); ?>">
                                    <a href="<?= esc_url(home_url($base_url_opportunities . $item_slug)) ?>">
                                        <?= esc_html($item_title); ?>
                                    </a>
                                </h3>
                            </div>
                        <?php endif; ?>
                        <div class="<?= esc_attr($classes['content']); ?>">
                            <?php if ($description) : ?>
                                <div class="<?= esc_attr($classes['description']); ?>">
                                    <?= esc_html($description); ?>
                                </div>
                            <?php endif; ?>
                            <div class="<?= esc_attr($classes['block-photo']); ?>">
                                <figure class="<?= esc_attr($classes['photo']); ?>">
                                    <img src="<?= esc_url($photo_url); ?>" class="<?= esc_attr($classes['photo-img']); ?>" alt="<?= esc_attr($photo_alt) ?>">
                                </figure>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
            <a href="<?= esc_url($btn_url) ?>" class="btn <?= esc_attr($classes['btn2']); ?>"><?= esc_html($btn_text); ?></a>
        <?php endif; ?>
    </div>
</section>