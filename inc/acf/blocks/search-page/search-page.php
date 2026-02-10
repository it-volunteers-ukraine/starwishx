<?php
$default_classes = [

    'section' => 'section',
    'title' => 'title',
    'block-filter' => 'block-filter',
    'filter' => 'filter',
    'filter-title' => 'filter-title',
    'filter-data' => 'filter-data',
    'sort-icon' => 'sort-icon',
    'content' => 'content',
    'newscards' => 'newscards',
    
    
    'my-select' => 'my-select',
];

// $category_slug = get_query_var('news_cat');
// if (!$category_slug) {
//     echo '<p>Категория не указана.</p>';
//     get_footer();
//     exit;
// }

// $category = 'category-oportunities';
// $term = get_term_by('slug', $category_slug, $category);
// if ($term && !is_wp_error($term)) {
//     $term_id   = $term->term_id; // ID категории
//     $term_name = $term->name;    // Название категории
// }
// echo 'tem_id: ' . $term_id . '<br>';
// echo 'term_name: ' . $term_name . '<br>';

// $title = esc_html(get_field('title')) . ' "' . $term_name . '"';


$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
// $title = get_field('title');

// $news_by_category = get_field('news_by_category');

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['search-page'] ?? []);
}

// -----------------------------
// 2. Определяем пагинацию и количество на страницу
// -----------------------------
$page = isset($_GET['page_num']) ? max(1, (int) $_GET['page_num']) : 1;
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 12;
if (!in_array($per_page, [12, 8, 4])) {
    $per_page = 12;
}
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$title = esc_html(get_field('title')) . ' "' . $search_term . '"';
$found = esc_html(get_field('found'));
$sort_title = esc_html(get_field('sort_title')) . ': ';
$sort_date_new = esc_html(get_field('sort_date_new'));
$sort_date_old = esc_html(get_field('sort_date_old'));


// print($search_term);
// echo 'page: ' . $page . '<br>';
// echo 'per_page: ' . $per_page . '<br>'; 
// echo 'category_slug: ' . $category_slug . '<br>';


// -----------------------------
// 3. Запрос WP_Query
// -----------------------------
// $post_type = ['news'];

$post_type = ['news', 'opportunity'];
// передать post_type в META для пагинации
update_post_meta(get_the_ID(), 'post_type', $post_type);

$args = [
    'post_type'      => $post_type, // Или свой CPT
    's'              => $search_term,
    // 'fields'         => 'ids', // важно!
    // 'no_found_rows'  => false, // нужно для pagination
    'posts_per_page' => $per_page,
    'paged'          => $page,
    // 'tax_query'      => [
        // [
            // 'taxonomy' => $category, // Или твоя кастомная taxonomy
            // 'field'    => 'slug',
            // 'terms'    => $category_slug,
        // ]
    // ]
];

$query = new WP_Query($args);

// if ($query->have_posts()) {
//     // создаём массив для этой категории
//     $res_by_cat[$term->term_id] = [
//         'term_id'   => $term->term_id,
//         'term_name' => $term->name,
//         'posts'     => []
//     ];

//     foreach ($query->posts as $post_item) {
//         // можно добавить данные категории внутрь поста
//         $post_item->term_id   = $term->term_id;
//         $post_item->term_name = $term->name;

//         $res_by_cat[$term->term_id]['posts'][] = $post_item;
//     }
// }

wp_reset_postdata();

$total_posts = (int) $query->found_posts;
$found_str = $found . ': ' . $total_posts;
// echo 'total post: ' . $total_posts;

$posts_list = $query;
$posts = $posts_list->posts;
// print_r($post_list);
// echo '<pre>'; // Обертываем в теги для форматирования
// echo var_dump($posts);
// echo var_dump($query);
// echo '</pre>';

?>

<section class="section <?php echo esc_attr($classes['section']); ?> ">
    <div class="container ">
        <h1 class="h3 <?php echo esc_attr($classes['title']); ?>"><?php echo esc_html($title); ?></h1>
        <div class="text-r <?php echo esc_attr($classes['block-filter']); ?>" >
            <p><?php echo $found_str; ?></p>
            <div class="<?php echo esc_attr($classes['filter']); ?>">
                <div class="<?php echo esc_attr($classes['filter-title']); ?>"><?php echo esc_html($sort_title); ?></div>
                <!-- <?php $sort_data = $sort_date_new; ?> -->
                <div class="custom-select btn-text-medium <?php echo esc_attr($classes['my-select']); ?>">
                    <select name="" id="">
                        <option value="0">Спочатку нові</option>
                        <option value="DESC">Спочатку нові</option>
                        <option value="ФІС">Спочатку старі</option>
                    </select>
                    <svg class="<?php echo esc_attr($classes["sort-icon"]); ?>">
                        <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-arrow_down"></use>
                    </svg>
                </div>
            </div>

        </div>
        <div class="cards-list <?php echo esc_attr($classes['newscards']); ?>">
            <?php if ($posts) : ?>
                <?php foreach ($posts as $item) : ?>
                    <?php
                    $item->term_id = $term_id ?? "";
                    get_template_part(
                        'template-parts/new-card',
                        null,
                        [
                            'item'    => $item,
                            'no_desc'   => true,
                        ]
                    );
                    ?>
                <? endforeach; ?>
            <? endif; ?>
        </div>
    </div>
</section>


</body>