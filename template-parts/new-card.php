<?php

/**
 * News card component
 *
 * @var array $args
 */
$err_no_field = "No field";

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
$post_id = $item->ID;
$term_id = $item->term_id ?? null;

$item_date  = date('d.m.Y', strtotime($item->post_date));
$item_title = get_field('title', $post_id);
$item_desc  = $no_desc ? false : get_field('description', $post_id);

$photo = get_field('photo', $post_id);

if (!$item_title) {
    $item_title = get_the_title($post_id) ?: $err_no_field;
}   

if ($photo){
    $photo_url = $photo['sizes']['large'] ?? '';
    $photo_alt = $photo['title'] ?: ($photo['title'] ?? '');
} elseif (has_post_thumbnail($post_id)){
    $photo_url = get_the_post_thumbnail_url($post_id, 'medium');
    $photo_alt = get_the_post_thumbnail_caption($post_id) ?: get_the_title($post_id);
} else {
    $photo_url =  get_template_directory_uri() . '/assets/img/card-placeholder.png';
    $photo_alt = $item_title;
}

$category_current_color = my_category_colors($term_id);
$label_color_text       = $category_current_color['label_color_text'];
$label_color_background = $category_current_color['label_color_background'];
$label_color_border     = $category_current_color['label_color_border'];

$item_label = esc_html($item->term_name ?? 'No category');

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