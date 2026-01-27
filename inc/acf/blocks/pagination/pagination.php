<?php

/**
 * Pagination for news by category
 * URL format:
 * ?page_num=2&per_page=8
 */

$default_classes = [
    'section' => 'section',
    'pagination-section' => 'pagination-section',
    'selected' => 'selected',
    'link' => 'link',
    'link-disabled' => 'link-disabled',
    'arrow-icon' => 'arrow-icon',

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
$base_url = home_url($wp->request);
$category = 'category-oportunities';
$category_slug = get_query_var('news_cat');
// echo 'category_slug: ' . $category_slug . '<br>';

// -----------------------------
// 2. Params
// -----------------------------
$page = isset($_GET['page_num']) ? max(1, (int) $_GET['page_num']) : 1;

$allowed_per_page = [12, 8, 4];
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 12;
$per_page = in_array($per_page, $allowed_per_page) ? $per_page : 12;

$query = new WP_Query([
    'post_type'      => 'news',
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
// print_r($query);
$total_posts = (int) $query->found_posts;
$total_pages = (int) ceil($total_posts / $per_page);
echo 'total_posts: ' . $total_posts . '<br>';
echo 'total_pages: ' . $total_pages . '<br>';

// -----------------------------
// 3. Fake total pages (optional)
// -----------------------------
// Если не знаешь сколько страниц — можно убрать стрелки
$total_pages = 999; // или null

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
        <nav class="pagination">

            <!-- Prev -->
            <?php if ($page == 1): ?>
            <?php endif; ?>
            <?php $prev_disabled = $page == 1 ? $classes['link-disabled'] : ''; ?>
            <a href="<?= pagination_url($base_url, $page - 1, $per_page); ?>" class="<?php echo $prev_disabled; ?>" rel="prev">
                &lt;
            </a>

            <!-- Numbers -->
            <?php
            $page_i = $page == 1 ? 1 : $page - 1;
            $page_i_end = $page == 1 ? 3 : $page + 1;
            for ($i = $page_i; $i <= $page_i_end; $i++): ?>
                <?php
                $link_disabled = $total_pages && $i > $total_pages ? $classes['link-disabled'] : '';
                $current_page_class = $page == $i ? $classes['selected'] : '';
                ?>
                <a href="<?= pagination_url($base_url, $i, $per_page); ?> <?php echo $link_disabled; ?>"
                    class="<?php echo $current_page_class; ?>">
                    <?= $i; ?>
                </a>
            <?php endfor; ?>

            <!-- Next -->
            <a href="<?= pagination_url($base_url, $page + 1, $per_page); ?>" rel="next">
                &gt;
            </a>

        </nav>
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
    </div>
</section>