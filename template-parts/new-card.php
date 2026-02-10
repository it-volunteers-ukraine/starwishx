<?php

/**
 * News card component
 *
 * @var array $args
 */

$item   = $args['item'];
$classes = $args['classes'] ?? [];
$swiper = $args['swiper'] ?? false;
$is_card_lg = $args['is_card_lg'] ?? false;
if ($is_card_lg) {
    $no_desc = $args['no_desc'] ?? true;
    $no_photo = $args['no_photo'] ?? true;
} else {
    $no_desc = $args['no_desc'] ?? false;
    $no_photo = $args['no_photo'] ?? false;
}
$card_lg_class = $is_card_lg ? ' newcard-lg ' : '';

$swiper_class = $swiper ? 'swiper-slide' : '';
// echo '<pre>';
// print_r($item);
// echo '</pre>';

$post_id = $item->ID;
$term_id = $item->term_id ?? null;

$item_date  = date('d.m.Y', strtotime($item->post_date));
$item_title = get_field('title', $post_id);
$item_desc  = $no_desc ? false : get_field('description', $post_id);

$photo = get_field('photo', $post_id);
$photo_url = $photo['sizes']['large'] ?? '';
$photo_alt = $photo['alt'] ?: ($photo['title'] ?? '');

$categories_colors = get_field('categories_labels_color', 'options');

// if ($term_id != ""){

// }
$category_current_color = get_category_by_id($categories_colors, $term_id);

$label_color_text       = $category_current_color['label_color_text'] ?? 'white';
$label_color_background = $category_current_color['label_color_background'] ?? 'grey';
$label_color_border     = $category_current_color['label_color_border'] ?? 'grey';

$item_label = esc_html($item->term_name ?? '');;


?>

<div class="<?php echo $swiper_class; ?> newcard-content <?php echo $card_lg_class; ?>">
    <?php if (!$no_photo) : ?>
        <div class="newcard-img-wrap ">
            <img
                src="<?php echo esc_url($photo_url); ?>"
                class="newcard-img "
                alt="<?php echo esc_attr($photo_alt); ?>">
            <div
                class="newcard-label"
                style="
                    --label-color: <?php echo esc_attr($label_color_text); ?>;
                    --label-bg: <?php echo esc_attr($label_color_background); ?>;
                    --label-border: <?php echo esc_attr($label_color_border); ?>;
                ">
                <?php echo $item_label; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="text-small newcard-date">
        <?php echo esc_html($item_date); ?>
    </div>

    <div class="subtitle-text-m newcard-title">
        <?php echo esc_html($item_title); ?>
    </div>

    <?php if ($item_desc) : ?>
        <div class="text-r newcard-text">
            <?php echo esc_html($item_desc); ?>
        </div>
    <?php endif; ?>
</div>