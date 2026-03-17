<?php

/**
 * News sidebar — latest news list.
 *
 * Two modes:
 *   1. Pre-queried: pass $args['posts'] — zero internal DB queries.
 *      Used by archive-news.php where the data layer already ran the query.
 *   2. Self-sufficient: omit $args['posts'] — queries latest news internally.
 *      Used by single-opportunity.php, single-project.php, or any template
 *      that just needs a "latest news" sidebar without managing queries.
 *
 * @var array $args {
 *     @type WP_Post[] $posts       Pre-queried post objects. If empty, queries internally.
 *     @type int       $count_news  Number of posts to fetch (self-sufficient mode). Default 8.
 *     @type string    $title       Optional section title.
 *     @type string    $title_class CSS class for title element. Default 'h3'.
 *     @type int       $line_clamp  CSS line-clamp for titles. Default 3.
 * }
 */

declare(strict_types=1);

$args        = $args ?? [];
$posts       = $args['posts'] ?? [];
$title       = $args['title'] ?? null;
$title_class = $args['title_class'] ?? 'h3';
$line_clamp  = $args['line_clamp'] ?? 3;

// Self-sufficient mode: query latest news when no posts are passed
if (empty($posts)) {
    $count_news = $args['count_news'] ?? 8;

    $query = new WP_Query([
        'post_type'              => 'news',
        'posts_per_page'         => $count_news,
        'orderby'                => 'date',
        'order'                  => 'DESC',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'tax_query'              => [[
            'taxonomy' => 'category-oportunities',
            'operator' => 'EXISTS',
        ]],
    ]);

    $posts = $query->posts;
    wp_reset_postdata();
}

if (empty($posts)) {
    return;
}
?>

<?php if ($title) : ?>
    <h2 class="lnew-main_title <?php echo esc_attr($title_class); ?>">
        <?php echo esc_html($title); ?>
    </h2>
<?php endif; ?>

<?php foreach ($posts as $post_item) : ?>
    <a href="<?php echo esc_url(get_permalink($post_item)); ?>" class="lnew-item">
        <div class="text-small lnew-date">
            <?php echo esc_html(get_the_date('d.m.Y', $post_item)); ?>
        </div>
        <div class="subtitle-text-m lnew-title" style="--line-clamp: <?php echo esc_attr((string) $line_clamp); ?>;">
            <?php echo esc_html(get_the_title($post_item)); ?>
        </div>
    </a>
<?php endforeach; ?>