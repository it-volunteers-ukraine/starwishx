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
    'btn2' => 'btn2'

];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
$title = get_field('title');
$label_text = get_field('label_text');
$btn_text = get_field('btn_text');
$btn_url = get_field('btn_page');

$is_mode_click_for_touch = get_field('mode_click_for_touch');

$terms = get_terms([
    'taxonomy' => 'categories-news',
    'hide_empty' => false
]);


$results = [];

// Получение с каждой категории по последнему посту
// foreach ($terms as $term) {
//     $query = new WP_Query([
//         'post_type' => 'news',
//         'posts_per_page' => 1,
//         'tax_query' => [
//             [
//                 'taxonomy' => 'categories-news',
//                 'field' => 'term_id',
//                 'terms' => $term->term_id
//             ]
//         ],
//         'orderby' => 'date',
//         'order' => 'DESC'
//     ]);

//     if ($query->have_posts()) {
//         $post_item = $query->posts[0];

//         // добавляем категорию внутрь объекта
//         $term_post = get_the_terms($post_item->ID, 'categories-news');
//         $post_item->term_id = $term_post ? $term_post[0]->term_id : null;
//         $post_item->term_name = $term_post ? $term_post[0]->name : null;

//         // $post_item->category_term = get_the_terms($post_item->ID, 'categories-news')[0]['term_id    '] ?? null;

//         $results[] = $post_item;
//     }

//     wp_reset_postdata();
// }

$query = new WP_Query([
    'post_type'      => 'news',
    'posts_per_page' => 6,   // сколько нужно вывести
    'orderby'        => 'date',
    'order'          => 'DESC',
    'tax_query'      => [
        [
            'taxonomy' => 'categories-news',
            'operator' => 'EXISTS'
        ]
    ]
]);

if ($query->have_posts()) {
    foreach ($query->posts as $post_item) {

        $term_post = get_the_terms($post_item->ID, 'categories-news');
        $post_item->term_id = $term_post ? $term_post[0]->term_id : null;
        $post_item->term_name = $term_post ? $term_post[0]->name : null;

        $results[] = $post_item;
    }
}

wp_reset_postdata();

$items = $results;

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['news'] ?? []);
}
?>

<section class="section <?php echo esc_attr($classes['section']); ?> ">
    <div class="container ">
        <?php if ($items) : ?>
            <div class="<?php echo esc_attr($classes['subtitle']); ?>"><?php echo esc_html($label_text); ?></div>
            <div class="<?php echo esc_attr($classes['title-wrap']); ?>">
                <h2 class="h2-big <?php echo esc_attr($classes['title']); ?>"><?php echo esc_html($title); ?></h2>
                <a href="<?php esc_url($btn_url); ?>" class="btn <?php echo esc_attr($classes['btn1']); ?>"><?php echo esc_html($btn_text); ?></a>
            </div>

            <div class="<?php echo esc_attr($classes['list']); ?>">
                <?php foreach ($items as $item) : ?>
                    <?php
                    $post_id = $item->ID;
                    $term_id = $item->term_id;
                    $term_full = get_term($term_id);
                    $item_taxonomy = $term_full->taxonomy;
                    $label_color_text = get_field('label_color_text', $item_taxonomy . '_' . $term_id);
                    $label_color_background = get_field('label_color_background', $item_taxonomy . '_' . $term_id);
                    $term_name = $item->term_name;
                    $item_date = date('d.m.Y', strtotime($item->post_date));
                    $item_title = get_field('title', $post_id);

                    $item_label = esc_html($term_name);
                    $photo = get_field('photo', $post_id);
                    $photo_url = $photo["sizes"]['large'];
                    $photo_alt = $photo['alt'] ?: $photo['title'];

                    ?>
                    <div class="<?php echo esc_attr($classes['item']); ?>">
                        <div class="<?php echo esc_attr($classes['content']); ?>">
                            <div class="<?php echo esc_attr($classes['photo-wrap']); ?>">
                                <img src="<?php echo esc_url($photo_url); ?>" class="<?php echo  esc_attr($classes['photo-img']); ?>" alt="<?php echo $photo_alt; ?>">
                                <div class="<?php echo esc_attr($classes['item-label']); ?>" style="--label-color: <?php echo $label_color_text; ?>; --label-bg: <?php echo $label_color_background; ?>; "><?php echo $item_label; ?></div>

                            </div>
                            <div class="text-small <?php echo esc_attr($classes['item-date']); ?>"><?php echo $item_date; ?></div>
                            <?php if ($item_title) : ?>
                                <div class="btn-text-medium <?php echo esc_attr($classes['item-title']); ?>">
                                    <?php echo $item_title; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="<?php esc_url($btn_url); ?>" class="btn <?php echo esc_attr($classes['btn2']); ?>"><?php echo esc_html($btn_text); ?></a>
        <?php endif; ?>
    </div>
</section>
<?php
?>