<?php
$default_classes = [

    'section' => 'section',
    'title' => 'title',
    'content' => 'content',
    'newscards' => 'newscards',


];

$post_type = my_post_type();
// echo 'post_type: ' ;
// print_r($post_type) . '<br>';

// update_post_meta(get_the_ID(), 'post_type', $post_type);

// echo '$_GET[$name]: ' . $_GET['news_cat'] . '<br>';

$category_slug = get_query_var('news_cat');
if (!$category_slug) {
    echo '<p>Категория не указана.</p>';
    get_footer();
    exit;
}
$category_name = my_category_by_slug($category_slug)->name;
$title = esc_html(get_field('title')) . ' "' . $category_name . '"';


// $category = 'category-oportunities';
// $term = get_term_by('slug', $category_slug, $category);
// if ($term && !is_wp_error($term)) {
//     $term_id   = $term->term_id; // ID категории
//     $term_name = $term->name;    // Название категории
// }


$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['news-page-category'] ?? []);
}


$query_args = my_query_args_prepare([]);
$query = my_query_search($query_args);
// echo '<br>=======================================<br>';
// echo 'query_args: <br>';
// echo '<pre>';   
// print_r($query_args);
// echo '</pre>';


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


<!-- </body> -->