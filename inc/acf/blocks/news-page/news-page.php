<?php
$default_classes = [

    'section' => 'section',
    'title' => 'title',
    'content' => 'content',
    'lastnews-content' => 'lastnews-content',

    'aside' => 'aside',
    'lnew-item' => 'lnew-item',
    'lnew-title' => 'lnew-title',
    'lnew-date' => 'lnew-date',


    'lastcards' => 'lastcards',
    'newscards-item' => 'newscards-item',
    'newcard-content' => 'newcard-content',
    'newcard-img-wrap' => 'newcard-img-wrap',
    'newcard-img' => 'newcard-img',
    'newcard-label' => 'newcard-label',
    'newcard-date' => 'newcard-date',
    'newcard-title' => 'newcard-title',
    'newcard-text' => 'newcard-text',

    'item' => 'item',
    'btn1' => 'btn1',
    'btn2' => 'btn2'

];


$category = 'categories-news';
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
$title = get_field('title');
// $label_text = get_field('label_text');
// $btn_text = get_field('btn_text');
// $btn_url = get_field('btn_page');

$is_mode_click_for_touch = get_field('mode_click_for_touch');

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['news-page'] ?? []);
}

$terms = get_terms([
    'taxonomy' => $category,
    'hide_empty' => false
]);

$results = [];
$query = new WP_Query([
    'post_type'      => 'news',
    'posts_per_page' => 8,   // сколько нужно вывести
    'orderby'        => 'date',
    'order'          => 'DESC',
    'tax_query'      => [
        [
            'taxonomy' => $category,
            'operator' => 'EXISTS'
        ]
    ]
]);

if ($query->have_posts()) {
    foreach ($query->posts as $post_item) {

        $term_post = get_the_terms($post_item->ID, $category);
        $post_item->term_id = $term_post ? $term_post[0]->term_id : null;
        $post_item->term_name = $term_post ? $term_post[0]->name : null;

        $results[] = $post_item;
    }
}

wp_reset_postdata();

$news_last = $results;

$res_last_by_cat = [];
// Получение с каждой категории по последнему посту
foreach ($terms as $term) {
    $query = new WP_Query([
        'post_type' => 'news',
        'posts_per_page' => 1,
        'tax_query' => [
            [
                'taxonomy' => $category,
                'field' => 'term_id',
                'terms' => $term->term_id
            ]
        ],
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    if ($query->have_posts()) {
        $post_item = $query->posts[0];

        // добавляем категорию внутрь объекта
        $term_post = get_the_terms($post_item->ID, $category);
        $post_item->term_id = $term_post ? $term_post[0]->term_id : null;
        $post_item->term_name = $term_post ? $term_post[0]->name : null;

        // $post_item->category_term = get_the_terms($post_item->ID, 'categories-news')[0]['term_id    '] ?? null;

        $res_last_by_cat[] = $post_item;
    }

    wp_reset_postdata();
}

$last_one_by_category = $res_last_by_cat;

$res_by_cat = [];
// Получение с каждой категории по последнему посту
foreach ($terms as $term) {
    $query = new WP_Query([
        'post_type' => 'news',
        'posts_per_page' => 7,
        'tax_query' => [
            [
                'taxonomy' => $category,
                'field' => 'term_id',
                'terms' => $term->term_id
            ]
        ],
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    if ($query->have_posts()) {
        $post_item = $query->posts[0];

        // добавляем категорию внутрь объекта
        $term_post = get_the_terms($post_item->ID, $category);
        $post_item->term_id = $term_post ? $term_post[0]->term_id : null;
        $post_item->term_name = $term_post ? $term_post[0]->name : null;

        // $post_item->category_term = get_the_terms($post_item->ID, 'categories-news')[0]['term_id    '] ?? null;

        $res_by_cat[] = $post_item;
    }

    wp_reset_postdata();
}

$res_by_cat = $res_by_cat;

?>

<section class="section <?php echo esc_attr($classes['section']); ?> ">
    <div class="container ">
        <h2 class="h3 <?php echo esc_attr($classes['title']); ?>"><?php echo esc_html($title); ?></h2>
        <div class="<?php echo esc_attr($classes['lastnews-content']); ?>">
            <div class="<?php echo esc_attr($classes['aside']); ?>">
                <?php if ($news_last) : ?>
                    <?php foreach ($news_last as $item) : ?>
                        <?php
                        $post_id = $item->ID;
                        $term_id = $item->term_id;
                        $term_full = get_term($term_id);
                        $item_taxonomy = $term_full->taxonomy;
                        $term_name = $item->term_name;
                        $item_date = date('d.m.Y', strtotime($item->post_date));
                        $item_title = get_field('title', $post_id);
                        ?>
                        <div class="<?php echo esc_attr($classes['lnew-item']); ?>">
                            <div class="text-small <?php echo esc_attr($classes['lnew-date']); ?>"><?php echo $item_date; ?></div>
                            <div class="subtitle-text-m <?php echo esc_attr($classes['lnew-title']); ?>">
                                <?php echo $item_title; ?>
                            </div>
                        </div>
                    <? endforeach; ?>
                <? endif; ?>
            </div>
            <div class="<?php echo esc_attr($classes['newscards']); ?>">
                <?php foreach ($last_one_by_category as $item) : ?>
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
                    $item_desc = get_field('description', $post_id);

                    $item_label = esc_html($term_name);
                    $photo = get_field('photo', $post_id);
                    $photo_url = $photo["sizes"]['large'];
                    $photo_alt = $photo['alt'] ?: $photo['title'];

                    ?>
                    <div class="<?php echo esc_attr($classes['newscards-item']); ?>">
                        <div class="<?php echo esc_attr($classes['newcard-content']); ?>"> <!-- Проверить может лишнее -->
                            <div class="<?php echo esc_attr($classes['newcard-img-wrap']); ?>">
                                <img src="<?php echo esc_url($photo_url); ?>" class="<?php echo  esc_attr($classes['newcard-img']); ?>" alt="<?php echo $photo_alt; ?>">
                                <div class="<?php echo esc_attr($classes['newcard-label']); ?>" style="--label-color: <?php echo $label_color_text; ?>; --label-bg: <?php echo $label_color_background; ?>; "><?php echo $item_label; ?></div>

                            </div>
                            <div class="text-small <?php echo esc_attr($classes['newcard-date']); ?>"><?php echo $item_date; ?></div>
                            <div class="subtitle-text-m <?php echo esc_attr($classes['newcard-title']); ?>">
                                <?php echo $item_title; ?>
                            </div>
                            <div class="text-r <?php echo esc_attr($classes['newcard-text']); ?>">
                                <?php echo $item_desc; ?>
                            </div>
                        </div>
                    </div>
                <? endforeach; ?>
            </div>

        </div>

    </div>
</section>