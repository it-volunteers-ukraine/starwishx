<?php
$default_classes = [

    'section' => 'section',
    'title' => 'title',
    'content' => 'content',
    'lastnews-content' => 'lastnews-content',

    'aside' => 'aside',

    'newscards-sw' => 'newscards-sw',
    'newscards-sw-wr' => 'newscards-sw-wr',

    'bycat-section' => 'bycat-section',
    'cat-title' => 'cat-title',
    'bycat-content' => 'bycat-content',
    'bycat-first-item' => 'bycat-first-item',
    'bycat-other-item' => 'bycat-other-item',
    'bycat-btn' => 'bycat-btn',

    // 'item' => 'item',
    'btn' => 'btn',
    'cat-link' => 'cat-link',

    'swiper-paginations' => 'swiper-paginations',



];

global $wp;
global $post;
$base_url = home_url($wp->request);
$news_page = $post;
// print_r($news_page);
$childrens = get_children([
    'post_parent' => $news_page->ID,
    'post_type'   => 'page',
    'post_status' => 'publish',
]);
// print_r($chldren);
// echo '<pre>'; // Обертываем в теги для форматирования
// echo var_dump($childrens);
// echo '</pre>';
// echo 'news_page: ' . $news_page;
// echo 'base_url=' . esc_url($base_url);
$category_base_url = get_permalink(reset($childrens)->ID);
// echo 'children=' . esc_url($children);

$category = 'category-oportunities';
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
$title = get_field('title');
$categories_colors = get_field('categories_labels_color', 'options');
// print_r($categories_colors);
// $category_current_color = get_category_by_id($categories_colors, $term_id);

$news_by_category = get_field('news_by_category');


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

// получение последних новостей
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

// добавляем данные категории внутрь поста
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

// Получение новостей по категориям для блока ниже
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
            'term_slug' => $term->slug,
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

<section class="section <?php echo esc_attr($classes['section']); ?> ">
    <div class="container ">
        <h2 class="h3 <?php echo esc_attr($classes['title']); ?>"><?php echo esc_html($title); ?></h2>
        <div class="<?php echo esc_attr($classes['lastnews-content']); ?>">
            <div class="<?php echo esc_attr($classes['aside']); ?>">
                <?php
                get_template_part(
                    'template-parts/last-news-aside',
                    null,
                    [
                        'count_news'    => 8,
                        'title'         => null,
                        'line_clamp'    => 3,
                    ]
                );
                ?>
            </div>

            <div class="<?php echo esc_attr($classes['newscards']); ?>">
                <?php if ($last_one_by_category) : ?>
                    <?php foreach ($last_one_by_category as $item) : ?>
                        <?php
                        get_template_part(
                            'template-parts/new-card',
                            null,
                            [
                                'item'    => $item,
                                'classes' => $classes,
                            ]
                        );

                        ?>

                    <? endforeach; ?>
                <? endif; ?>
            </div>

        </div>
        <div class="swiper mySwiper <?php echo esc_attr($classes['newscards-sw']); ?>">
            <div class="swiper-wrapper <?php echo esc_attr($classes['newscards-sw-wr']); ?>">
                <?php if ($last_one_by_category) : ?>
                    <?php foreach ($last_one_by_category as $item) : ?>
                        <?php
                        get_template_part(
                            'template-parts/new-card',
                            null,
                            [
                                'item'    => $item,
                                'classes' => $classes,
                                'swiper'  => true,
                            ]
                        );
                        ?>
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
                    $btn_text = esc_html(get_field('button_text'));
                    $cat_name = $res_by_cat[$cat_id['category']]['term_name'];
                    $cat_slug = $res_by_cat[$cat_id['category']]['term_slug'];
                    $category_url = $category_base_url . $cat_slug . '/';
                    ?>
                    <a href="<?php echo $category_url; ?>" class="<?php echo esc_attr($classes['cat-link']); ?> ">
                        <h2 class="h5 <?php echo esc_attr($classes['cat-title']); ?>"><?php echo esc_html($cat_name); ?></h2>
                    </a>
                    <div class="<?php echo esc_attr($classes['bycat-content']); ?>">
                        <?php
                        $post_list = $res_by_cat[$cat_id['category']]['posts'];
                        ?>
                        <div class="<?php echo esc_attr($classes['bycat-first-item']); ?> ">
                            <?php
                            get_template_part(
                                'template-parts/new-card',
                                null,
                                [
                                    'item'    => $post_list[0],
                                    'classes' => $classes,
                                    'swiper'  => false,
                                    'is_card_lg' => true,
                                    "no_desc" => true,
                                    'no_photo' => false,
                                ]
                            );
                            ?>
                        </div>
                        <div class="<?php echo esc_attr($classes['bycat-other-item']); ?> ">
                            <?php for ($i = 1; $i < count($post_list); $i++) : ?>
                                <?php
                                get_template_part(
                                    'template-parts/new-card',
                                    null,
                                    [
                                        'item'    => $post_list[$i],
                                        'classes' => $classes,
                                        'swiper'  => false,
                                        'is_card_lg' => true,
                                        "no_desc" => true,
                                        'no_photo' => true,
                                    ]
                                );
                                ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <a href="<?php echo $category_url; ?>" class="btn <?php echo esc_attr($classes['bycat-btn']); ?>"><?php echo esc_html($btn_text); ?></a>
                </div>
            </section>
        <?php endforeach; ?>
        </в>
    <?php endif; ?>

    </body>