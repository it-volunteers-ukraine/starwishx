<?php

/**
 * Pagination for news by category
 * URL format:
 * ?page_num=2&per_page=8
 */

$default_classes = [
    'section' => 'section',
    'pagination' => 'pagination',
    'pagination-section' => 'pagination-section',
    'selected' => 'selected',
    'link' => 'link',
    'link-disabled' => 'link-disabled',
    'arrow-icon' => 'arrow-icon',
    'load-more' => 'load-more',

];

$classes = $default_classes;
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['pagination'] ?? []);
}

// -----------------------------
// 1. Current URL
// -----------------------------
global $wp;
global $post;

// echo '<pre>';
// print_r($wp);
// echo '</pre>';

// echo '<pre>';
// print_r($post);
// echo '</pre>';


$wp_request = $wp->request;
$base_url = home_url($wp->request);
$post_name = $post->post_name;
// echo 'base_url: ' . $base_url . '<br>';
$category = 'category-oportunities';
$category_slug = get_query_var('news_cat');

$path = trim($wp->request, '/');
$parts = explode('/', $path);

$post_type = $parts[0] ?? null;

// -----------------------------
// 2. Params
// -----------------------------
$page = isset($_GET['page_num']) ? max(1, (int) $_GET['page_num']) : 1;

$allowed_per_page = [12, 8, 4];
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 12;
$per_page = in_array($per_page, $allowed_per_page) ? $per_page : 12;

// echo 'request: ' . $wp_request . '<br>';
// echo 'post_type: ' . $post_type . '<br>';
// echo 'post_name: ' . $post_name . '<br>';
// echo 'category: ' . $category . '<br>';
// echo 'category_slug: ' . $category_slug . '<br>';
// echo 'page: ' . $page . '<br>';
// echo 'per_page: ' . $per_page . '<br>';
// -----------------------------

$query = new WP_Query([
    'post_type'      => $post_type,
    'posts_per_page' => $per_page,
    'paged'          => 1,
    'fields'         => 'ids', // важно!
    'no_found_rows'  => false, // нужно для pagination
    'tax_query'      => [
        [
            'taxonomy' => $category,
            'field'    => 'slug',
            'terms'    => $category_slug,
        ]
    ],
]);

// echo '<pre>';
// print_r($query);
// echo '</pre>';

$total_posts = (int) $query->found_posts;
$total_pages = (int) ceil($total_posts / $per_page);
// echo 'total_posts: ' . $total_posts . '<br>';
// echo 'total_pages: ' . $total_pages . '<br>';

// -----------------------------
// 3. Fake total pages (optional)
// -----------------------------
// Если не знаешь сколько страниц — можно убрать стрелки
// $total_pages = 999; // или null

// -----------------------------
// 4. URL builder
// -----------------------------
function pagination_url($base_url, $page, $per_page)
{
    return esc_url(add_query_arg([
        'page_num' => $page,
        'per_page' => $per_page,
    ], $base_url));
}

?>

<section class="section breadcumbs-section <?php echo esc_attr($classes["section"]); ?> ">
    <div class="container">
        <nav class="<?php echo esc_attr($classes["pagination"]); ?> ">

            <!-- Prev -->
            <?php if ($page == 1): ?>
            <?php endif; ?>
            <?php $prev_disabled = $page == 1 ? true : false; ?>

            <div>
                <a id='pagination-prev'
                    href="<?= pagination_url($base_url, $page - 1, $per_page); ?>" 
                    class="" 
                    data-link-disabled=<?php echo $prev_disabled; ?>
                    rel="prev">
                    &lt;
                </a>

                <!-- Numbers -->
                <?php
                $page_i = $page == 1 ? 1 : $page - 1;
                $page_i_end = $page == 1 ? 3 : $page + 1;
                $count = 1;
                for ($i = $page_i; $i <= $page_i_end; $i++): ?>
                    <?php
                    $link_disabled = $total_pages && $i > $total_pages ? true : false;
                    $current_page_class = $page == $i ? $classes['selected'] : '';
                    $is_active = $page == $i ? true : false;
                    ?>
                    <a id='pagination-<?php echo $count; ?>' href="<?= pagination_url($base_url, $i, $per_page); ?>"
                        data-is-active="<?php echo $is_active; ?>"
                        data-link-disabled="<?php echo $link_disabled ? 1 : 0; ?>"
                        class="">
                        <?= $i; ?>
                    </a>
                    <?php $count++; ?>
                <?php endfor; ?>

                <!-- Next -->
                <?php $next_disabled = $total_pages && $page >= $total_pages ? $classes['link-disabled'] : ''; ?>
                <a id='pagination-next' href="<?= pagination_url($base_url, $page + 1, $per_page); ?>" class="<?php echo $next_disabled; ?> " rel="next">
                    &gt;
                </a>

            </div>
            <?php
            $load_next_page = $page < $total_pages ? $page + 1 : $total_pages;
            $load_more_disabled = $total_pages && $page >= $total_pages ? $classes['link-disabled'] : '';
            $load_more_hidden = $total_pages && $page >= $total_pages ? "display: none" : 0; ;
            ?>
            <button
                id="load-more"
                type="button"
                data-post-type=<?php echo $post_name; ?>
                data-page="<?php echo $page; ?>"
                data-category="<?= esc_attr(get_query_var('news_cat')); ?>"
                data-per-page="<?= esc_attr($per_page); ?>"
                style = "<?php echo $load_more_hidden; ?>"
                class="btn <?php echo esc_attr($classes["load-more"]); ?>  <?php echo esc_attr($load_more_disabled); ?>">
                Load more
            </button>
            <form method="get" class="per-page">

                <input type="hidden" name="page_num" value="1">

                <select name="per_page" onchange="this.form.submit()">
                    <?php foreach ([4, 8, 12] as $value): ?>
                        <option value="<?= $value; ?>" <?= selected($per_page, $value, false); ?>>
                            <?= $value; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            </form>



        </nav>
    </div>
</section>