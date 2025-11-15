<?php

/**
 * Template Name: Policy Page
 *
 * Shared policy page template for Content Policy / Privacy Policy
 *
 * ACF fields expected (attach this field group to this template):
 * - policy_pdf_link (file, return ID)
 * - policy_pdf_title (text) — optional, falls back to attachment title
 * - policy_date (date_picker)
 * - policy_intro (wysiwyg or textarea) — short intro shown above main content
 *
 * Replace 'your-textdomain' with your theme textdomain.
 */

$td = '_themedomain';
get_header();

// Template-specific body class (optional)
add_filter('body_class', function ($classes) {
    $classes[] = 'page-template-policy';
    return $classes;
});

// Get post ID (for get_field)
$post_id = get_the_ID();

// ACF fields (we assume ACF is present)
$pdf_id = get_field('policy_pdf_link', $post_id);
$pdf_title_override = get_field('policy_pdf_title', $post_id);
$policy_date_raw = get_field('policy_date', $post_id);
$policy_intro = get_field('policy_intro', $post_id);

// Derived values & safeguards
$pdf_url = '';
$pdf_filename = '';
$pdf_title = '';
$pdf_filesize_readable = '';
$pdf_icon_svg = ''; // we'll use a sprite SVG for the icon
if ($pdf_id) {
    $pdf_id = intval($pdf_id);
    $pdf_url = wp_get_attachment_url($pdf_id);
    $pdf_filename = get_post_meta($pdf_id, '_wp_attached_file', true) ? wp_basename(get_attached_file($pdf_id)) : wp_basename($pdf_url);
    // title fallback: user-specified override or attachment title
    $attachment_title = get_the_title($pdf_id);
    $pdf_title = $pdf_title_override ? $pdf_title_override : ($attachment_title ? $attachment_title : $pdf_filename);

    // filesize (guarded)
    $attached_path = get_attached_file($pdf_id);
    if ($attached_path && file_exists($attached_path)) {
        $pdf_filesize_readable = size_format(filesize($attached_path));
    }

    // sprite url for icon (use theme dir)
    $sprite_path = get_template_directory_uri() . '/assets/img/sprites.svg#icon-pdf';
    $pdf_icon_svg = '<svg class="icon-pdf" width="22" height="28" aria-hidden="true" focusable="false"><use xlink:href="' . esc_url($sprite_path) . '"></use></svg>';
}

// Format date: parse saved value and output localized string & machine datetime
$policy_date_attr = '';
$policy_date_readable = '';
if ($policy_date_raw) {
    // ACF date_picker return format should be configured; try strtotime
    $time = strtotime($policy_date_raw);
    if ($time !== false) {
        $policy_date_attr = date('c', $time);
        $policy_date_readable = date_i18n('j F Y', $time); // localized
    }
}

// Breadcrumb/back link: try referer, fallback to site home
$referer = wp_get_referer();
$back_url = $referer ? $referer : home_url();
$back_label = __('Повернутись назад', $td); // Ukrainian label (translateable)
?>

<main id="main" class="site-main">

    <div class="container policy-page">
        <nav class="policy-breadcrumbs">
            <a href="<?php echo esc_url($back_url); ?>" class="policy-breadcrumbs-back">
                <svg width="13" height="16" class="icon-arrow-left">
                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-long_arrow_left"></use>
                </svg> <span><?php echo esc_html($back_label); ?><span></a>
        </nav>
        <?php if (get_the_title()) : ?>
            <h1 id="policy-title-<?php echo esc_attr($post_id); ?>" class="policy-title h3"><?php the_title(); ?></h1>
        <?php endif; ?>

        <section class="policy-layout" aria-labelledby="policy-title-<?php echo esc_attr($post_id); ?>">
            <aside class="policy-left" aria-labelledby="policy-pdf-meta-<?php echo esc_attr($post_id); ?>">
                <?php if ($pdf_url) : ?>
                    <a class="policy-pdf-link" href="<?php echo esc_url($pdf_url); ?>" target="_blank" rel="noopener noreferrer" download aria-describedby="policy-pdf-meta-<?php echo esc_attr($post_id); ?>">
                        <span class="policy-pdf-icon" aria-hidden="true">
                            <?php echo $pdf_icon_svg; // safe: constructed above 
                            ?>
                        </span>

                        <span class="policy-pdf-info" id="policy-pdf-meta-<?php echo esc_attr($post_id); ?>">
                            <span class="policy-pdf-title text-small"><?php echo esc_html($pdf_title); ?></span>
                            <span class="policy-pdf-meta text-small">
                                <?php
                                // show "PDF, 12Mb" (localized)
                                if ($pdf_filesize_readable) {
                                    /* translators: 1: PDF label, 2: file size (eg 12 Mb) */
                                    echo esc_html(sprintf(__('%1$s, %2$s', $td), 'PDF', $pdf_filesize_readable));
                                } else {
                                    echo esc_html__('PDF', $td);
                                }
                                ?>
                            </span>
                        </span>
                    </a>
                <?php else: ?>
                    <div class="policy-pdf-empty"><?php echo esc_html__('No PDF uploaded', $td); ?></div>
                <?php endif; ?>
            </aside>

            <article class="policy-right">

                <?php if ($policy_date_readable) : ?>
                    <div class="policy-effective-date">
                        <?php
                        /* translators: %s - effective date */
                        printf(
                            /* translators: Effective date label */
                            esc_html__('Дата набуття чинності: %s', $td),
                            '<time datetime="' . esc_attr($policy_date_attr) . '">' . esc_html($policy_date_readable) . '</time>'
                        );
                        ?>
                    </div>
                <?php endif; ?>

                <?php if ($policy_intro) : ?>
                    <div class="policy-intro">
                        <?php echo wp_kses_post($policy_intro); ?>
                    </div>
                <?php endif; ?>

                <div class="policy-content">
                    <?php
                    // Use Gutenberg native content
                    while (have_posts()) : the_post();
                        the_content();
                    endwhile;
                    // Reset postdata is not necessary here (we're in main loop)
                    ?>
                </div>
            </article>
        </section>
    </div>

</main>

<?php
get_footer();
