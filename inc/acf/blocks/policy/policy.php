<?php

/**
 * Policy block template - now uses page content instead of ACF fields
 *
 * Expected content sources:
 * - Page Title: $post->post_title (used as main policy title)
 * - Page Content: $post->post_content (used as policy content)
 * - Page PDF Attachments: Get from page attachments
 * - Page Date: $post->post_date (used as effective date)
 */

// textdomain
$td = '_themedomain';

/**
 * Block ID safe for DOM
 */
$block_id = (isset($block) && ! empty($block['id'])) ? $block['id'] : 'policy-' . uniqid();

// classes (merge with modules.json when exists)
$default_classes = [
    'policy' => 'policy',
    'container' => 'container',
    'inner' => 'policy-inner',
    'left' => 'policy-left',
    'right' => 'policy-right',
    'suptitle' => 'suptitle',
    'title' => 'title',
    'date' => 'policy-date',
    'intro' => 'policy-intro',
    'content' => 'policy-content',
    'pdf' => 'policy-pdf-link'
];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    if (isset($modules['policy']) && is_array($modules['policy'])) {
        $classes = array_merge($default_classes, $modules['policy']);
    }
}

// Get page content
global $post;
$page_title = $post->post_title ?? '';
$page_content = $post->post_content ?? '';
$page_date = $post->post_date ?? '';

// Find PDF attachments from the page
$attachments = get_attached_media('application/pdf', $post->ID);
$first_pdf = reset($attachments); // Get the first PDF attachment

// Get page featured image for PDF icon
$featured_image_id = get_post_thumbnail_id($post->ID);
$pdf_icon_url = '';

if ($first_pdf) {
    // Use the PDF's mime type icon
    $pdf_icon_url = wp_mime_type_icon($first_pdf->ID);
} else {
    // Fallback to theme's PDF icon
    $pdf_icon_url = get_template_directory_uri() . '/assets/icons/pdf.svg';
}

// Format the page date for display
$effective_date_readable = '';
$effective_date_attr = '';
if ($page_date) {
    $time = strtotime($page_date);
    if ($time !== false) {
        // ISO 8601 for datetime attr
        $effective_date_attr = date('c', $time);
        // localized human readable (use date_i18n to respect WP locale)
        $effective_date_readable = date_i18n('j F Y', $time);
    }
}

// Extract intro from page content (first paragraph or first 200 chars)
$intro = '';
if ($page_content) {
    // Remove HTML tags and get first part
    $content_text = wp_strip_all_tags($page_content);
    $intro = mb_substr($content_text, 0, 200);
    if (mb_strlen($content_text) > 200) {
        $intro .= '...';
    }
}

// IDs for accessibility
$title_id = $block_id . '-title';
$pdf_meta_id = $block_id . '-pdf-meta';

?>

<section id="<?php echo esc_attr($block_id); ?>" class="<?php echo esc_attr($classes['policy']); ?>" aria-labelledby="<?php echo esc_attr($title_id); ?>">
    <div class="<?php echo esc_attr($classes['container']); ?>">
        <div class="<?php echo esc_attr($classes['inner']); ?>">

            <!-- complementary content (PDF) -->
            <aside class="<?php echo esc_attr($classes['left']); ?>" aria-labelledby="<?php echo esc_attr($pdf_meta_id); ?>">
                <?php if ($first_pdf) : ?>
                    <a class="<?php echo esc_attr($classes['pdf']); ?>"
                        href="<?php echo esc_url(wp_get_attachment_url($first_pdf->ID)); ?>"
                        target="_blank" rel="noopener noreferrer"
                        download>
                        <span class="pdf-icon" aria-hidden="true">
                            <img src="<?php echo esc_url($pdf_icon_url); ?>" alt="">
                        </span>

                        <span id="<?php echo esc_attr($pdf_meta_id); ?>" class="pdf-meta">
                            <strong><?php echo esc_html($first_pdf->post_title ?: sprintf(esc_html__('Document (%s)', $td), esc_html($first_pdf->post_name))); ?></strong>
                            <br>
                            <small><?php
                                    // display filesize and type
                                    $attached_path = get_attached_file($first_pdf->ID);
                                    if ($attached_path && file_exists($attached_path)) {
                                        $filesize_human = size_format(filesize($attached_path));
                                        echo esc_html(sprintf(esc_html__('%s · PDF', $td), $filesize_human));
                                    } else {
                                        echo esc_html__('PDF', $td);
                                    }
                                    ?></small>
                        </span>
                    </a>
                <?php else: ?>
                    <div class="pdf-empty" aria-hidden="true">
                        <?php echo esc_html__('No PDF uploaded', $td); ?>
                    </div>
                <?php endif; ?>
            </aside>

            <!-- main textual content -->
            <div class="<?php echo esc_attr($classes['right']); ?>">

                <?php if ($page_title) : ?>
                    <!-- Use h2 by default; page templates should control page-level H1 -->
                    <h2 id="<?php echo esc_attr($title_id); ?>" class="<?php echo esc_attr($classes['title']); ?>"><?php echo esc_html($page_title); ?></h2>
                <?php endif; ?>

                <?php if ($effective_date && $effective_date_readable) : ?>
                    <div class="<?php echo esc_attr($classes['date']); ?>">
                        <?php
                        /* translators: %s: effective date, e.g. 1 January 2025 */
                        printf(
                            esc_html__('Effective date: %s', $td),
                            '<time datetime="' . esc_attr($effective_date_attr) . '">' . esc_html($effective_date_readable) . '</time>'
                        );
                        ?>
                    </div>
                <?php endif; ?>

                <?php if ($intro) : ?>
                    <div class="<?php echo esc_attr($classes['intro']); ?>">
                        <?php echo wp_kses_post($intro); ?>
                    </div>
                <?php endif; ?>

                <?php if ($page_content) : ?>
                    <div class="<?php echo esc_attr($classes['content']); ?>">
                        <?php echo wp_kses_post($page_content); ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</section>