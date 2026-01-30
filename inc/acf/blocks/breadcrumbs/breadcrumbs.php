<?php
// http://starwishx.local/news-by-category/kultura-ta-khobi/?page_num=2&per_page=4
// http://starwishx.local/news/news-by-category/kultura-ta-khobi/?page_num=2&per_page=4
// Loading classes
$default_classes = [
    'section' => 'section',
    'breadcumbs-section' => 'breadcumbs-section',
    'list' => 'list',
    'item' => 'item',
    'selected' => 'selected',
    'link' => 'link',
    'arrow-icon' => 'arrow-icon',
    'home-icon' => 'home-icon'

];

$classes = $default_classes;
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['breadcrumbs'] ?? []);
}

global $post;

$breadcrumbs = [];

// --- Определяем категорию, если есть ---
$category = 'category-oportunities';
$category_slug = get_query_var('news_cat');
$term = null;
if ($category_slug) {
    $term = get_term_by('slug', $category_slug, $category);
}

$current_post = $post; // чтобы не ломать $post
while ($current_post) {
    // echo remove_query_arg( [], get_permalink( $current_post ) );
    // echo get_the_permalink($current_post);
    $breadcrumbs[] = [

        'title' => get_the_title($current_post),
        'link'  => get_permalink($current_post),
    ];

    if ($current_post->post_parent) {
        $current_post = get_post($current_post->post_parent);
    } else {
        $current_post = false;
    }
}
$breadcrumbs = array_reverse($breadcrumbs);

// --- Подмена последнего элемента на категорию, если есть ---
if ($term) {
    // Последний элемент массива — текущая страница
    $url = strtok(home_url(add_query_arg([])), '?');
    $url = rtrim($url, '/');

    $last_index = count($breadcrumbs) - 1;
    $breadcrumbs[$last_index] = [
        'title' => $term->name,
        'link'  => $url // последняя, активная, не кликабельная
    ];
}

// Home page
$home_id = get_option('page_on_front');
$home_url = get_the_permalink($home_id);
$home_title = get_the_title($home_id);

// Active item
$active_title = get_the_title();
?>

<section class="section breadcumbs-section <?php echo esc_attr($classes["section"]); ?> ">
    <div class="container">
        <nav>
            <ul class="text-r <?php echo esc_attr($classes["list"]); ?>">
                <li class="<?php echo esc_attr($classes["item"]); ?>">
                    <a class="link-bc" href="<?php echo esc_url($home_url); ?>">
                        <svg class="<?php echo esc_attr($classes["home-icon"]); ?>">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-house"></use>
                        </svg>
                    </a>
                </li>
                <?php foreach ($breadcrumbs as $crumb): ?>
                    <!-- <?php
                            echo '<pre>';
                            print_r($crumb);
                            echo '</pre>';
                            ?> -->
                    <li class="<?php echo esc_attr($classes["item"]); ?>">
                        <svg class="<?php echo esc_attr($classes["arrow-icon"]); ?>">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-arrow"></use>
                        </svg>
                        <a class="link-bc" href="<?php echo $crumb['link']; ?>">
                            <?php echo esc_html($crumb['title']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
</section>