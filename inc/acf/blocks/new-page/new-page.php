<?php
$default_classes = [

    'section' => 'section',
    'title' => 'title',
    'content' => 'content',
    'lastnews-content' => 'lastnews-content',

    'aside' => 'aside',

    'btn' => 'btn',

];

global $wp;
global $post;
$base_url = home_url($wp->request);
$news_page = $post;
$post_name = $post->post_name;
print_r($news_page);
print_r($wp);
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



?>

<section class="section <?php echo esc_attr($classes['section']); ?> ">
    <div class="container ">
        <h1 class="h1 <?php echo esc_attr($classes['title']); ?>"><?php echo esc_html($title); ?></h1>
        

    </div>
</section>




</body>