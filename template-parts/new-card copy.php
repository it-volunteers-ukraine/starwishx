<?php

/**
 * News card component
 *
 * @var array $args
 */

$item   = $args['item'];
$classes = $args['classes'] ?? [];
// echo '<pre>';
// print_r($item);
// echo '</pre>';

$post_id = $item->ID;
$term_id = $item->term_id;

$item_date  = date('d.m.Y', strtotime($item->post_date));
$item_title = get_field('title', $post_id);
$item_desc  = get_field('description', $post_id);

$photo = get_field('photo', $post_id);
$photo_url = $photo['sizes']['large'] ?? '';
$photo_alt = $photo['alt'] ?: ($photo['title'] ?? '');

$categories_colors = get_field('categories_labels_color', 'options');
$category_current_color = get_category_by_id($categories_colors, $term_id);

$label_color_text       = $category_current_color['label_color_text'] ?? 'white';
$label_color_background = $category_current_color['label_color_background'] ?? 'grey';
$label_color_border     = $category_current_color['label_color_border'] ?? 'grey';

$item_label = esc_html($item->term_name);
?>

<div class="<?php echo esc_attr($classes['newscards-item'] ?? 'newscards'); ?>">
    <div class="<?php echo esc_attr($classes['newcard-content'] ?? 'newcard-content'); ?>">
        <div class="<?php echo esc_attr($classes['newcard-img-wrap'] ?? 'newcard-img-wrap'); ?>">
            <img
                src="<?php echo esc_url($photo_url); ?>"
                class="<?php echo esc_attr($classes['newcard-img'] ?? 'newcard-img'); ?>"
                alt="<?php echo esc_attr($photo_alt); ?>">
            <div
                class="<?php echo esc_attr($classes['newcard-label'] ?? 'newcard-label'); ?>"
                style="
                    --label-color: <?php echo esc_attr($label_color_text); ?>;
                    --label-bg: <?php echo esc_attr($label_color_background); ?>;
                    --label-border: <?php echo esc_attr($label_color_border); ?>;
                ">
                <?php echo $item_label; ?>
            </div>
        </div>

        <div class="text-small <?php echo esc_attr($classes['newcard-date'] ?? 'newcard-date'); ?>">
            <?php echo esc_html($item_date); ?>
        </div>

        <div class="subtitle-text-m <?php echo esc_attr($classes['newcard-title'] ?? 'newcard-title'); ?>">
            <?php echo esc_html($item_title); ?>
        </div>

        <?php if ($item_desc) : ?>
            <div class="text-r <?php echo esc_attr($classes['newcard-text'] ?? 'newcard-text'); ?>">
                <?php echo esc_html($item_desc); ?>
            </div>
        <?php endif; ?>
    </div>
</div>