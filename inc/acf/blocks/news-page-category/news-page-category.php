<?php
$default_classes = [

    'section' => 'section',
    'title' => 'title',
    'content' => 'content',
    'newscards' => 'newscards',


];

$post_type = ['news'];
update_post_meta(get_the_ID(), 'post_type', $post_type);

$category_slug = get_query_var('news_cat');
if (!$category_slug) {
    echo '<p>Категория не указана.</p>';
    get_footer();
    exit;
}


$category = 'category-oportunities';
$term = get_term_by('slug', $category_slug, $category);
if ($term && !is_wp_error($term)) {
    $term_id   = $term->term_id; // ID категории
    $term_name = $term->name;    // Название категории
}
// echo 'tem_id: ' . $term_id . '<br>';
// echo 'term_name: ' . $term_name . '<br>';
$title = esc_html(get_field('title')) . ' "' . $term_name . '"';


$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
// $title = get_field('title');

// $news_by_category = get_field('news_by_category');

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['news-page-category'] ?? []);
}

// -----------------------------
// 2. Определяем пагинацию и количество на страницу
// -----------------------------
$page = isset($_GET['page_num']) ? max(1, (int) $_GET['page_num']) : 1;
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 12;
if (!in_array($per_page, [12, 8, 4])) {
    $per_page = 12;
}
// echo 'page: ' . $page . '<br>';
// echo 'per_page: ' . $per_page . '<br>'; 
// echo 'category_slug: ' . $category_slug . '<br>';


// -----------------------------
// 3. Запрос WP_Query
// -----------------------------
$args = [
    'post_type'      => 'news', // Или свой CPT
    'posts_per_page' => $per_page,
    'paged'          => $page,
    'tax_query'      => [
        [
            'taxonomy' => $category, // Или твоя кастомная taxonomy
            'field'    => 'slug',
            'terms'    => $category_slug,
        ]
    ]
];

$query = new WP_Query($args);

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

$posts_list = $query;
$posts = $posts_list->posts;
// print_r($post_list);
// echo '<pre>'; // Обертываем в теги для форматирования
// echo var_dump($posts);
// echo '</pre>';

?>

<section class="section <?php echo esc_attr($classes['section']); ?> ">
    <div class="container ">
        <h1 class="h3 <?php echo esc_attr($classes['title']); ?>"><?php echo esc_html($title); ?></h1>
        <div class="cards-list <?php echo esc_attr($classes['newscards']); ?>">
            <?php if ($posts) : ?>
                <?php foreach ($posts as $item) : ?>
                    <?php
                    $item->term_id = $term_id;
                    get_template_part(
                        'template-parts/new-card',
                        null,
                        [
                            'item'    => $item,
                            // 'classes' => $classes,
                        ]
                    );
                    ?>
                <? endforeach; ?>
            <? endif; ?>
        </div>
    </div>
</section>


</body>