<?php

/**
 * News card component
 *
 * @var array $args
 */

if (!function_exists('my_post_rating')) {
    function my_post_rating($post_item)
    {
        if(!$post_item){
            return null;
        }
        $rating = get_post_meta($post_item->ID);
        // print_r($post_item);

        if (isset($rating['_opportunity_rating_avg'][0])) {
            $rating = $rating['_opportunity_rating_avg'][0];
        } elseif ($post_item->post_type === 'opportunity') {
            // echo "!!!!";
            $rating = 0;
        } else {
            $rating = null;
        }
        return $rating;
    }
}

$err_no_field = "No field";

$item   = $args['item'];
// echo '<pre>';
// print_r($item);
// echo '</pre>';
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
//card version img size, title size
$card_version = $args['card_version'] ?? 1;
$class_card_version = 'card-version-' . $card_version;
$class_title = $card_version == 2 ? 'big-text-semibold' : 'subtitle-text-m';

$swiper_class = $swiper ? 'swiper-slide' : '';
$post_id = $item->ID ?? '';
$permalink = get_permalink($post_id) ?? '';
$term_id = $item->term_id ?? null;

$item_date  = date('d.m.Y', strtotime($item->post_date));

$item_acf_title = get_field('title', $post_id);
$item_title = $item_acf_title ? $item_acf_title : get_the_title($post_id);
$item_acf_desc = get_field('description', $post_id);
$item_def_desc = get_the_excerpt($post_id);
$item_desc = $item_acf_desc ? $item_acf_desc : $item_def_desc;
$item_desc  = $no_desc ? false : $item_desc;

$photo = get_field('photo', $post_id);

if ($photo) {
    $photo_url = $photo['sizes']['large'] ?? '';
    $photo_alt = $photo['title'] ?: ($photo['title'] ?? '');
} elseif (has_post_thumbnail($post_id)) {
    $photo_url = get_the_post_thumbnail_url($post_id, 'medium');
    $photo_alt = get_the_post_thumbnail_caption($post_id) ?: get_the_title($post_id);
} else {
    $photo_url =  get_template_directory_uri() . '/assets/img/card-placeholder.png';
    $photo_alt = $item_title;
}
$comments = get_approved_comments($post_id);
// echo 'comments: ' . count($comments) . '<br>';
// echo '<pre>';
// print_r($comments);
// echo '</pre>';

$post_rating = my_post_rating($item);
// echo 'post_rating: ' . $post_rating . '<br>';
if (is_numeric($post_rating)) {
    $post_rating = ceil($post_rating); // Округляем рейтинг до целого числа для отображения звезд
} else {
    $post_rating = null; // Если рейтинг не числовой, устанавливаем его в null
}

$category_current_color = my_category_colors($term_id);
$label_color_text       = $category_current_color['label_color_text'];
$label_color_background = $category_current_color['label_color_background'];
$label_color_border     = $category_current_color['label_color_border'];

$item_label = esc_html($item->term_name ?? 'No category');

?>

<div class="<?php echo $swiper_class; ?> newcard-content <?php echo $card_lg_class . ' ' . $class_card_version; ?> "
    data-post-id="<?php echo esc_attr($post_id); ?>"
    data-term-id="<?php echo esc_attr($term_id); ?>">
    <?php if (!$no_photo) : ?>
        <a href="<?php echo $permalink; ?>" class="newcard-img-wrap ">
            <!-- <a href="<?php echo $permalink; ?>"> -->
            <img
                src="<?php echo esc_url($photo_url); ?>"
                class="newcard-img "
                alt="<?php echo esc_attr($photo_alt); ?>">
            <div class="newcard-label"
                style="
                    --label-color: <?php echo esc_attr($label_color_text); ?>;
                    --label-bg: <?php echo esc_attr($label_color_background); ?>;
                    --label-border: <?php echo esc_attr($label_color_border); ?>;
                    ">
                <?php echo $item_label; ?>
            </div>
            <!-- </a> -->
        </a>
    <?php endif; ?>
    <div class="newcard-info">
        <?php if (is_numeric($post_rating)) : ?>
            <div class="comment-rating" data-wp-bind--hidden="!context.item.rating">
                <!-- CSS Driven Display stars -->
                <div class="stars-display" data-wp-bind--data-rating="context.item.rating">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <?php // Выводим звезду, заполняя ее в зависимости от рейтинга 
                        if ($s <= $post_rating) {
                            $star_class = 'icon-star stars';
                        } else {
                            $star_class = 'icon-star';
                        }
                        ?>
                        <svg class="<?php echo $star_class; ?>">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-star"></use>
                        </svg>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
        <div class="text-small newcard-date">
            <?php echo esc_html($item_date); ?>
        </div>
    </div>

    <a href="<?php echo $permalink; ?>" class="link-def <?php echo $class_title; ?> newcard-title" title="<?php echo $item_title; ?>">
        <?php echo esc_html($item_title); ?>
    </a>

    <?php if ($item_desc) : ?>
        <div class="text-r newcard-text" title="<?php echo $item_desc; ?>">
            <?php echo esc_html($item_desc); ?>
        </div>
    <?php endif; ?>
</div>