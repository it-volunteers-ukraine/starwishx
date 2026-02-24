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


    // 'my-select' => 'my-select',
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

// $qqq = get_query_var('news_cat');
// echo 'get_query_var(news_cat): ';
// print_r($qqq);
// echo '<br>';


$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
$sortby_list = get_field('sortby_list', 'options');
$sortby_type_default = $sortby_list['default_sort'] ?? 'date';
$sort_default = $sortby_list['sort_by_type'] ?? 'DESC';
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
$sortby = isset($_GET['sortby']) ? $_GET['sortby'] : $sortby_type_default;
$sort = isset($_GET['order']) ? $_GET['order'] : $sort_default;

$title = esc_html(get_field('title')) . ' "' . $search_term . '"';
$found = esc_html(get_field('found'));
$sort_title = esc_html(get_field('sort_title')) . ': ';
$sort_date_new = esc_html(get_field('sort_date_new'));
$sort_date_old = esc_html(get_field('sort_date_old'));


// print($search_term);
// echo 'page: ' . $page . '<br>';
// echo 'per_page: ' . $per_page . '<br>'; 
// echo 'category_slug: ' . $category_slug . '<br>';

// $post_type = my_post_type(); // функция для получения всех типов записей, участвующих в поиске
// передать post_type в META для пагинации
// update_post_meta(get_the_ID(), 'post_type', $post_type);


// echo 'args_query: ' . '<br>';
// echo '<pre>';
// print_r($args_query);
// echo '</pre>';


// $args = [
//     'post_type'      => $post_type, // Или свой CPT
//     's'              => $search_term,
//     // 'fields'         => 'ids', // важно!
//     'no_found_rows'  => false, // нужно для pagination
//     'orderby'        => $sortby,
//     'order'          => $sort,
//     'posts_per_page' => $per_page,
//     'paged'          => $page,
//     // 'tax_query'      => [
//     // [
//     // 'taxonomy' => $category, // Или твоя кастомная taxonomy
//     // 'field'    => 'slug',
//     // 'terms'    => $category_slug,
//     // ]
//     // ]
// ];

$args_query = my_query_args_prepare([]);
$query = my_query_search($args_query);

$total_posts = (int) $query->found_posts;
$posts = $query->posts;

?>

<section class="section <?php echo esc_attr($classes['section']); ?> ">
    <div class="container ">
        <h1 class="h3 <?php echo esc_attr($classes['title']); ?>"><?php echo esc_html($title); ?></h1>
        <?php get_template_part('template-parts/sortby-and-result-posts', null, ['total_posts' => $total_posts]); ?>

        <div class="cards-list <?php echo esc_attr($classes['newscards']); ?>">
            <?php if ($posts) : ?>
                <?php foreach ($posts as $item) : ?>
                    <?php
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
