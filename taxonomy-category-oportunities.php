<?php

/**
 * Taxonomy archive for taxonomy `category-oportunities`.
 * Place this file in your theme root to control the term archive (/category-oportunities/{term}/).
 * It lists posts from `opportunity` and `news` post types assigned to the current term.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

$term = get_queried_object();
if (! $term || is_wp_error($term)) {
    // fallback: show 404
    get_template_part('404');
    exit;
}

// ACF image for term
$acf_image = null;
if (function_exists('get_field')) {
    $acf_image = get_field('cat_oportunity_image', $term->taxonomy . '_' . $term->term_id);
}

?>

<main id="main" class="site-main" role="main">
    <div class="container taxonomy-category-oportunities">
        <header class="term-header">
            <?php if (! empty($acf_image) && is_array($acf_image)) :
                if (! empty($acf_image['ID'])) :
                    echo wp_get_attachment_image($acf_image['ID'], 'large', false, ['alt' => esc_attr($acf_image['alt'] ?? $term->name)]);
                else :
                    printf('<img src="%s" alt="%s" loading="lazy" />', esc_url($acf_image['url']), esc_attr($acf_image['alt'] ?? $term->name));
                endif;
            endif; ?>

            <h1 class="term-title"><?php echo esc_html($term->name); ?></h1>
            <?php if (! empty($term->description)) : ?>
                <div class="term-description-wrap">
                    <div class="term-description"><?php echo wp_kses_post(wpautop($term->description)); ?></div>
                </div>
            <?php endif; ?>


        </header>

        <?php
        // Show child terms (if any)
        $children = get_terms([
            'taxonomy'   => $term->taxonomy,
            'parent'     => $term->term_id,
            'hide_empty' => true,
        ]);

        if (! empty($children) && ! is_wp_error($children)) : ?>
            <nav class="term-children" aria-label="Subcategories">
                <ul>
                    <?php foreach ($children as $child) : ?>
                        <li><a href="<?php echo esc_url(get_term_link($child)); ?>"><?php echo esc_html($child->name); ?> <span class="count">(<?php echo (int) $child->count; ?>)</span></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>

        <?php
        // Query posts of both post types assigned to this term
        $paged = get_query_var('paged') ? absint(get_query_var('paged')) : 1;
        $args = [
            'post_type'      => [
                'opportunity',
                // 'news'
            ],
            'tax_query'      => [
                [
                    'taxonomy' => $term->taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ],
            ],
            'posts_per_page' => 10,
            'paged'          => $paged,
        ];

        $list_q = new WP_Query($args);

        if ($list_q->have_posts()) : ?>
            <div class="term-posts-list">
                <?php while ($list_q->have_posts()) : $list_q->the_post();
                    get_template_part('template-parts/content', 'opportunity-list');
                endwhile; ?>
            </div>

            <nav class="pagination" aria-label="Pagination">
                <?php
                echo paginate_links([
                    'total'   => $list_q->max_num_pages,
                    'current' => $paged,
                ]);
                ?>
            </nav>
        <?php else :
            get_template_part('template-parts/content', 'none');
        endif;

        wp_reset_postdata();
        ?>
    </div>
</main>

<?php
get_footer();
?>