<?php

/**
 * Last News component
 *
 * @var array $args
 */

$count_news = $args['count_news'] ?? 8;
$title = $args['title'] ?? null;
$line_clamp = $args['line_clamp'] ?? 3;

$category = 'category-oportunities';
$post_type = 'news';

$results = [];
$query = new WP_Query([
    'post_type'      => $post_type,
    'posts_per_page' => $count_news,   // сколько нужно вывести
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
?>

<?php if ($news_last) : ?>
    <?php if ($title) : ?>
        <h2 class="h3 title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
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
        <div class="lnew-item">
            <div class="text-small lnew-date"><?php echo $item_date; ?></div>
            <div class="subtitle-text-m lnew-title " style="--line-clamp: <?php echo esc_attr($line_clamp); ?>;">
                <?php echo $item_title; ?>
            </div>
        </div>
    <? endforeach; ?>
<? endif; ?>