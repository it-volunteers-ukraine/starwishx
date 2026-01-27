<?php
/*
Template Name: Category Oportunities Index
Description: Lists top-level terms for the `category-oportunities` taxonomy, showing each term's ACF image (field name: `cat_oportunity_image`) and a simple list of child terms (no images).
Place this file in your theme (or child theme) and assign the "Category Oportunities Index" template to a Page with slug `category-oportunities`.

ACF requirements:
- Field group `taxonomy-oportunities` with an image field `cat_oportunity_image` must be active and assigned to the taxonomy `category-oportunities` (you provided this field group).
- The image return format for the field should be `array` (the template expects an ACF image array).
*/

if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="main" class="site-main" role="main">
    <div class="container cat-oportunities-page">
        <header class="page-header">
            <h1 class="page-title"><?php echo esc_html(get_the_title() ?: 'Category Oportunities'); ?></h1>
            <!-- <p class="page-subtitle">Top-level category index for opportunities.</p> -->
        </header>

        <?php
        $terms = get_terms([
            'taxonomy'   => 'category-oportunities',
            'parent'     => 0,            // top-level terms only
            'hide_empty' => true,         // change to false to show empty terms
        ]);

        if (! empty($terms) && ! is_wp_error($terms)) :
        ?>
            <ul class="cat-oportunities-list" aria-label="Category Oportunities list">
                <?php foreach ($terms as $term) :
                    $term_link = esc_url(get_term_link($term));

                    // ACF term image: expects return 'array'
                    $acf_image = null;
                    if (function_exists('get_field')) {
                        $acf_image = get_field('cat_oportunity_image', $term->taxonomy . '_' . $term->term_id);
                    }

                    // Per-post-type counts using WP_Query->found_posts
                    $opportunity_q = new WP_Query([
                        'post_type'      => 'opportunity',
                        'tax_query'      => [
                            [
                                'taxonomy' => 'category-oportunities',
                                'field'    => 'term_id',
                                'terms'    => $term->term_id,
                            ],
                        ],
                        'posts_per_page' => 1,
                        'fields'         => 'ids',
                        'no_found_rows'  => false, // allow found_posts to be populated
                    ]);
                    $opportunity_count = (int) $opportunity_q->found_posts;
                    wp_reset_postdata();

                    $news_q = new WP_Query([
                        'post_type'      => 'news',
                        'tax_query'      => [
                            [
                                'taxonomy' => 'category-oportunities',
                                'field'    => 'term_id',
                                'terms'    => $term->term_id,
                            ],
                        ],
                        'posts_per_page' => 1,
                        'fields'         => 'ids',
                        'no_found_rows'  => false,
                    ]);
                    $news_count = (int) $news_q->found_posts;
                    wp_reset_postdata();

                    // Child terms (simple list, no images)
                    $children = get_terms([
                        'taxonomy'   => 'category-oportunities',
                        'parent'     => $term->term_id,
                        'hide_empty' => true,
                    ]);
                ?>
                    <li class="cat-oportunities-item">

                        <a class="cat-oportunities-link" href="<?php echo $term_link; ?>">
                            <figure class="cat-oportunities-image">
                                <?php if (! empty($acf_image) && is_array($acf_image)) :
                                    // Use wp_get_attachment_image for responsive markup when attachment ID is available
                                    if (! empty($acf_image['ID'])) :
                                        echo wp_get_attachment_image($acf_image['ID'], 'medium', false, ['alt' => esc_attr($acf_image['alt'] ?? $term->name)]);
                                    else :
                                        // Fallback: build img tag from the ACF array URL
                                        printf('<img src="%s" alt="%s" loading="lazy" />', esc_url($acf_image['url']), esc_attr($acf_image['alt'] ?? $term->name));
                                    endif;
                                endif; ?>
                                <figcaption class="cat-oportunities-image__caption">
                                    <div class="cat-oportunities-meta-wrap cat-oportunities-meta-wrap--label">
                                        <span class="cat-oportunities-name"><?php echo esc_html($term->name); ?></span>
                                        <span class="cat-oportunities-count">(<?php echo $opportunity_count + $news_count; ?>)</span>
                                    </div>
                                </figcaption>
                                <?php if (! empty($term->description)) : ?>
                                    <div class="cat-oportunities-description-wrap">
                                        <p class="cat-oportunities-description">
                                            <?php echo wp_kses_post(wp_trim_words($term->description, 28, '...')); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </figure>
                        </a>
                        <div class="cat-oportunities-posttype-meta--top">
                            <small>
                                <?php echo sprintf('Opportunities: %d', $opportunity_count); ?>
                            </small>
                        </div>
                        <?php if (! empty($children) && ! is_wp_error($children)) : ?>
                            <ul class="cat-oportunities-children" aria-label="Subcategories of <?php echo esc_attr($term->name); ?>">
                                <?php foreach ($children as $child) : ?>
                                    <li class="cat-oportunities-child-item">
                                        <a href="<?php echo esc_url(get_term_link($child)); ?>">
                                            <?php echo esc_html($child->name); ?>
                                            <span class="child-count">(<?php echo (int) $child->count; ?>)</span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <!-- 
                    <div class="cat-oportunities-posttype-meta">
                        <small>
                            < ?php echo sprintf('Opportunities: %d — News: %d', $opportunity_count, $news_count); ? >
                        </small>
                    </div> -->
                        <div class="cat-oportunities-posttype-meta">
                            <small>
                                <?php echo sprintf('News by category: %d', $news_count); ?>
                            </small>
                        </div>

                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p>No categories found.</p>
        <?php endif; ?>
    </div>
</main>

<?php
get_footer();
?>