<?php

/**
 * Template Name: Policy Page
 *
 * Shared policy page template for Content Policy / Privacy Policy.
 *
 * ACF fields expected (attach this field group to this template):
 *   - policy_pdf_link  (file, return format: ID)
 *   - policy_pdf_title (text) — optional override for the PDF link label
 *   - policy_date      (date_picker, return format: Ymd)
 *   - policy_intro     (wysiwyg or textarea) — intro shown above post content
 *
 * Requires: inc/theme-helpers.php
 */

// FIX — body_class: must be conditional on this template only, otherwise
// 'page-template-policy' is added to every single page on the site.
add_filter('body_class', function (array $classes): array {
    if (is_page_template('templates/page-policy.php')) {
        $classes[] = 'page-template-policy';
    }
    return $classes;
});

$post_id = get_the_ID();

// --- ACF fields via sw_get_field() -------------------------------------------
// sw_get_field() guards function_exists('get_field') so the page degrades
// gracefully if ACF is deactivated, and normalises ACF's false → null.
// FIX — get_filter() typo on policy_date: get_filter() does not exist in WP,
// meaning $policy_date_raw was always null and the date block never rendered.
$pdf_id             = sw_get_field('policy_pdf_link',  $post_id);
$pdf_title_override = sw_get_field('policy_pdf_title', $post_id);
$policy_date_raw    = sw_get_field('policy_date',      $post_id);
$policy_intro       = sw_get_field('policy_intro',     $post_id);

// --- PDF resolution ----------------------------------------------------------
// sw_prepare_document() handles the full resolution chain: ID → url, title
// fallback (override → attachment title → filename), and two-tier filesize
// (attachment metadata for WP 6.0+ images; filesystem fallback for PDFs).
// Returns null when $pdf_id is empty, false, or points to a deleted attachment.
$doc = sw_prepare_document($pdf_id);

// --- Date formatting ---------------------------------------------------------
// sw_format_date_for_ui() uses DateTime::createFromFormat() with an explicit
// 'Ymd' format — no strtotime() ambiguity — and wp_date() for the display
// string to honour the WP Settings → General timezone.
$date = sw_format_date_for_ui($policy_date_raw, 'j F Y', true);
// $date['iso']     — ISO 8601 for <time datetime="...">
// $date['display'] — localized human-readable string

$pdf_title = $pdf_title_override ? $pdf_title_override : $doc['title'];

get_header();
?>

<!-- Render Breadcrumbs ACF block -->
<?php
// Kept as render_block() intentionally — alternative rendering methods
// (do_action hook, shortcode) strip the block styles on this theme.
if (function_exists('render_block')) {
    echo render_block([
        'blockName'   => 'acf/breadcrumbs',
        'attrs'       => [],
        'innerHTML'   => '',
        'innerBlocks' => [],
    ]);
}
?>

<main id="main" class="site-main">
    <div class="container policy-page">

        <?php
        // FIX — double query: original called get_the_title() in the condition
        // then the_title() inside (a second DB hit). Single call here, reused.
        // Also: the_title() echoes without escaping; esc_html() applied explicitly.
        $page_title = get_the_title($post_id);
        if ($page_title) :
        ?>
            <h1
                id="policy-title-<?php echo esc_attr($post_id); ?>"
                class="policy-title h3"><?php echo esc_html($page_title); ?></h1>
        <?php endif; ?>

        <section
            class="policy-layout"
            aria-labelledby="policy-title-<?php echo esc_attr($post_id); ?>">

            <aside
                class="policy-left"
                <?php
                // FIX — dangling ARIA reference: original used aria-labelledby pointing
                // to "policy-pdf-meta-{id}", an element that only exists when a PDF is
                // uploaded. Screen readers silently skip unresolved references.
                // Replaced with a static aria-label that is always present.
                ?>
                aria-label="<?php esc_attr_e('Policy document download', '_themedomain'); ?>">
                <?php if ($doc) : ?>

                    <a
                        class="policy-pdf-link"
                        href="<?php echo esc_url($doc['url']); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        download
                        aria-describedby="policy-pdf-meta-<?php echo esc_attr($post_id); ?>">
                        <span class="policy-pdf-icon" aria-hidden="true">
                            <?php
                            // sw_svg_e() applies wp_kses with a strict SVG allowlist.
                            // The icon-pdf sprite is 22×28 — non-square, second arg is width.
                            sw_svg_e('icon-pdf', 22, 28);
                            ?>
                        </span>

                        <span class="policy-pdf-info" id="policy-pdf-meta-<?php echo esc_attr($post_id); ?>">
                            <span class="policy-pdf-title text-small">
                                <?php esc_html_e($pdf_title); ?>
                            </span>
                            <span class="policy-pdf-meta text-small">
                                <?php
                                if ($doc['filesize']) {
                                    /* translators: 1: file type label (e.g. PDF), 2: file size (e.g. 1.2 MB) */
                                    echo esc_html(sprintf(
                                        __('%1$s, %2$s', '_themedomain'),
                                        'PDF',
                                        size_format($doc['filesize'])
                                    ));
                                } else {
                                    echo esc_html__('PDF', '_themedomain');
                                }
                                ?>
                            </span>
                        </span>
                    </a>

                <?php else : ?>
                    <div class="policy-pdf-empty">
                        <?php esc_html_e('No PDF uploaded', '_themedomain'); ?>
                    </div>
                <?php endif; ?>
            </aside>

            <article class="policy-right">

                <?php if ($date['iso']) : ?>
                    <div class="policy-effective-date">
                        <?php
                        // FIX — printf() escaping break: original passed the translatable
                        // string through esc_html__() then substituted raw <time> HTML via
                        // printf() — the escaping had already run, HTML passed straight through.
                        // sw_time_tag() guarantees esc_attr on datetime, esc_html on content.
                        echo esc_html__('Дата набуття чинності:', '_themedomain');
                        echo ' ';
                        echo sw_time_tag($date['iso'], $date['display']);
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
                    while (have_posts()) :
                        the_post();
                        the_content();
                    endwhile;
                    ?>
                </div>

            </article>

        </section>
    </div>
</main>

<?php get_footer(); ?>