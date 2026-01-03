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

    'newscards-sw' => 'newscards-sw',
    'newscards-sw-wr' => 'newscards-sw-wr',


    'lastcards' => 'lastcards',
    'newscards-item' => 'newscards-item',
    'newcard-content' => 'newcard-content',
    'newcard-img-wrap' => 'newcard-img-wrap',
    'newcard-img' => 'newcard-img',
    'newcard-label' => 'newcard-label',
    'newcard-date' => 'newcard-date',
    'newcard-title' => 'newcard-title',
    'newcard-text' => 'newcard-text',

    'bycat-section' => 'bycat-section',
    'cat-title' => 'cat-title',
    'bycat-content' => 'bycat-content',
    'bycat-item' => 'bycat-item',
    'bycat-first-item' => 'bycat-first-item',
    'bycat-other-item' => 'bycat-other-item',
    'bycat-img-wrap' => 'bycat-img-wrap',
    'bycat-img' => 'bycat-img',
    'bycat-date' => 'bycat-date',
    'bycat-title' => 'bycat-title',
    'bycat-btn' => 'bycat-btn',

    'item' => 'item',
    'btn' => 'btn',

    'swiper-paginations' => 'swiper-paginations',



];


$category = 'category-oportunities';
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
$title = get_field('title');
$categories_colors = get_field('categories_labels_color', 'options');
// print_r($categories_colors);

$news_by_category = get_field('news_by_category');
// print_r($news_by_category);
// foreach ($news_by_category as $cat_item) {
//     $cat_item['category'];
//     print_r($cat_item['category']);
//     // print_r($cat_item->category);
// }

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

function get_category_by_id($category_color, $category)
{
    foreach ($category_color as $cat_item) {
        if ($cat_item['category'] == $category) {
            return $cat_item;
        }
    }
}

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

        $res_last_by_cat[] = $post_item;
    }

    wp_reset_postdata();
}

$last_one_by_category = $res_last_by_cat;

$res_by_cat = [];

foreach ($terms as $term) {

    $query = new WP_Query([
        'post_type'      => 'news',
        'posts_per_page' => 7,
        'tax_query'      => [
            [
                'taxonomy' => $category,
                'field'    => 'term_id',
                'terms'    => $term->term_id
            ]
        ],
        'orderby'        => 'date',
        'order'          => 'DESC'
    ]);

    if ($query->have_posts()) {

        // создаём массив для этой категории
        $res_by_cat[$term->term_id] = [
            'term_id'   => $term->term_id,
            'term_name' => $term->name,
            'posts'     => []
        ];

        foreach ($query->posts as $post_item) {

            // можно добавить данные категории внутрь поста
            $post_item->term_id   = $term->term_id;
            $post_item->term_name = $term->name;

            $res_by_cat[$term->term_id]['posts'][] = $post_item;
        }
    }

    wp_reset_postdata();
}


$res_by_cat = $res_by_cat;
// echo '<pre>'; // Обертываем в теги для форматирования
// echo var_dump($res_by_cat);
// echo '</pre>';

?>
<?php
function render_card_bycat($item, $classes = [], $is_no_photo = false)
{

    $post_id   = $item->ID;
    $term_id   = $item->term_id;


    $item_date = date('d.m.Y', strtotime($item->post_date));
    $item_title = get_field('title', $post_id);


    $photo = get_field('photo', $post_id);
    $photo_url = $photo['sizes']['large'] ?? '';
    $photo_alt = $photo['alt'] ?: ($photo['title'] ?? '');
    $item_date = date('d.m.Y', strtotime($item->post_date));
    $categories_colors = get_field('categories_labels_color', 'options');
    $category_current_color = get_category_by_id($categories_colors, $term_id);
    $label_color_text = $category_current_color['label_color_text'];
    $label_color_background = $category_current_color['label_color_background'];
    $label_color_border = $category_current_color['label_color_border'];
    $term_name = $item->term_name;
    $item_label = esc_html($term_name);

    // вывод
?>
    <div class="<?php echo esc_attr($classes['bycat-item']); ?>">
        <?php if (!$is_no_photo) : ?>
            <div class="<?php echo esc_attr($classes['bycat-img-wrap']); ?>">
                <img src="<?php echo esc_url($photo_url); ?>"
                    class="<?php echo esc_attr($classes['bycat-img']); ?>"
                    alt="<?php echo esc_attr($photo_alt); ?>">
                <div class="<?php echo esc_attr($classes['newcard-label']); ?>" style="--label-color: <?php echo $label_color_text; ?>; --label-bg: <?php echo $label_color_background; ?>; --label-border: <?php echo $label_color_border; ?>; "><?php echo $item_label; ?></div>

            </div>
        <?php endif; ?>

        <div class="text-small <?php echo esc_attr($classes['bycat-date']); ?>">
            <?php echo esc_html($item_date); ?>
        </div>

        <div class="subtitle-text-m <?php echo esc_attr($classes['bycat-title']); ?>">
            <?php echo esc_html($item_title); ?>
        </div>
    </div>
<?php
}
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
                <?php if ($last_one_by_category) : ?>
                    <?php foreach ($last_one_by_category as $item) : ?>
                        <?php
                        $post_id = $item->ID;
                        $term_id = $item->term_id;
                        $term_full = get_term($term_id);
                        $item_taxonomy = $term_full->taxonomy;
                        $category_current_color = get_category_by_id($categories_colors, $term_id);
                        $label_color_text = $category_current_color['label_color_text'];
                        $label_color_background = $category_current_color['label_color_background'];
                        $label_color_border = $category_current_color['label_color_border'];
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
                                    <div class="<?php echo esc_attr($classes['newcard-label']); ?>" style="--label-color: <?php echo $label_color_text; ?>; --label-bg: <?php echo $label_color_background; ?>; --label-border: <?php echo $label_color_border; ?>; "><?php echo $item_label; ?></div>

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
                <? endif; ?>
            </div>

        </div>
        <div class="swiper mySwiper <?php echo esc_attr($classes['newscards-sw']); ?>">
            <div class="swiper-wrapper <?php echo esc_attr($classes['newscards-sw-wr']); ?>">
                <?php if ($last_one_by_category) : ?>
                    <?php foreach ($last_one_by_category as $item) : ?>
                        <?php
                        $post_id = $item->ID;
                        $term_id = $item->term_id;
                        $term_full = get_term($term_id);
                        $item_taxonomy = $term_full->taxonomy;
                        $category_current_color = get_category_by_id($categories_colors, $term_id);
                        $label_color_text = $category_current_color['label_color_text'];
                        $label_color_background = $category_current_color['label_color_background'];
                        $label_color_border = $category_current_color['label_color_border'];
                        $term_name = $item->term_name;
                        $item_date = date('d.m.Y', strtotime($item->post_date));
                        $item_title = get_field('title', $post_id);
                        $item_desc = get_field('description', $post_id);

                        $item_label = esc_html($term_name);
                        $photo = get_field('photo', $post_id);
                        $photo_url = $photo["sizes"]['large'];
                        $photo_alt = $photo['alt'] ?: $photo['title'];

                        ?>
                        <div class="swiper-slide <?php echo esc_attr($classes['newscards-item']); ?>">
                            <div class="<?php echo esc_attr($classes['newcard-content']); ?>"> <!-- Проверить может лишнее -->
                                <div class="<?php echo esc_attr($classes['newcard-img-wrap']); ?>">
                                    <img src="<?php echo esc_url($photo_url); ?>" class="<?php echo  esc_attr($classes['newcard-img']); ?>" alt="<?php echo $photo_alt; ?>">
                                    <div class="<?php echo esc_attr($classes['newcard-label']); ?>" style="--label-color: <?php echo $label_color_text; ?>; --label-bg: <?php echo $label_color_background; ?>; --label-border: <?php echo $label_color_border; ?>; "><?php echo $item_label; ?></div>

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
                <? endif; ?>
            </div>
            <div class="swiper-paginations <?php echo esc_attr($classes['swiper-paginations']); ?>"></div>
        </div>

    </div>
</section>


<?php if (count($news_by_category) > 0) : ?>
    <div>
        <?php foreach ($news_by_category as $cat_id) : ?>
            <section class="section <?php echo esc_attr($classes['section']); ?> <?php echo esc_attr($classes['bycat-section']); ?> ">
                <div class="container ">
                    <?php
                    $btn_url = '#';
                    $btn_text = esc_html(get_field('button_text'));
                    $cat_name = $res_by_cat[$cat_id['category']]['term_name'];
                    $label_color_text = $category_current_color['label_color_text'];
                    $label_color_background = $category_current_color['label_color_background'];
                    $label_color_border = $category_current_color['label_color_border'];
                    ?>
                    <h2 class="h5 <?php echo esc_attr($classes['cat-title']); ?>"><?php echo esc_html($cat_name); ?></h2>
                    <div class="<?php echo esc_attr($classes['bycat-content']); ?>">
                        <?php $conunt_post = 1; ?>
                        <?php
                        $post_list = $res_by_cat[$cat_id['category']]['posts'];
                        ?>
                        <div class="<?php echo esc_attr($classes['bycat-first-item']); ?> ">
                            <?php render_card_bycat($post_list[0], $classes); ?>
                        </div>
                        <div class="<?php echo esc_attr($classes['bycat-other-item']); ?> ">
                            <?php for ($i = 1; $i < count($post_list); $i++) : ?>
                                <?php render_card_bycat($post_list[$i], $classes, true); ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <a href="<?php esc_url($btn_url); ?>" class="btn <?php echo esc_attr($classes['bycat-btn']); ?>"><?php echo esc_html($btn_text); ?></a>
                </div>
            </section>
        <?php endforeach; ?>
        </в>
    <?php endif; ?>

    </body>