<?php

/**
 * Template part for showing a post inside the term-posts-list (used by taxonomy-category-oportunities.php)
 * File: template-parts/content-opportunity-list.php
 */

if (! defined('ABSPATH')) {
    exit;
}


// Get raw data (safe, loop-context aware)
$post_id       = get_the_ID();
$raw_date_ends   = get_post_meta($post_id, 'opportunity_date_ends', true);
// $date_object = new DateTime($raw_date_ends);
// $date_ends   =  $date_object->format('d.m.y');
if ($raw_date_ends) {
    $timestamp = strtotime($raw_date_ends);
    $date_ends = date('d.m.Y', $timestamp);
}
$raw_excerpt   = get_post_field('post_excerpt', $post_id, 'raw');
$raw_description = get_post_meta($post_id, 'opportunity_description', true) ?: '';
// Priority logic: Native excerpt > opportunity_description meta
// (Explicitly checks for non-whitespace content to avoid space-only strings)
$display_text = (! empty(trim($raw_excerpt)))
    ? $raw_excerpt
    : $raw_description;

?>
<article id="post-<?php the_ID(); ?>" <?php post_class('opportunity-list-item'); ?>>
    <a class="oli-link" href="<?php the_permalink(); ?>">
        <div class="oli-thumbnail">
            <?php if (has_post_thumbnail()) :
                the_post_thumbnail('medium');
            else :
                echo '<div class="oli-placeholder">No img</div>';
            endif; ?>
            <div class="oli-meta">
                <!-- <span class="oli-posttype">< ?php echo esc_html(get_post_type()); ? ></span> -->
                <!-- <span class="oli-date">< ?php echo get_the_date('d.m.y'); ? ></span> -->
                <span class="oli-date">Діє до: <?php echo $date_ends ?></span>
            </div>
        </div>

        <div class="oli-content">
            <h2 class="oli-title"><?php the_title(); ?></h2>

            <div class="oli-excerpt">
                <!-- < ?php echo wp_kses_post(wp_trim_words(get_the_excerpt(), 30, '...')); ? > -->
                <?php
                // Clean, truncate, and safely output
                echo wp_kses_post(wp_trim_words($display_text, 24, '...'));
                ?>
            </div>
        </div>
    </a>
</article>