<?php

/** 
 * Helpers functions
 * 
 * File: inc/theme-helpers.php
 */

declare(strict_types=1);

if (!defined('ABSPATH')) exit;

/**
 * =========================================================================
 * UTILITIES - Strings, Sanitization, Dates
 * ========================================================================= */

/**
 * @deprecated Use \Shared\Sanitize\InputSanitizer::containsUrl() directly.
 */
function sw_contains_url(string $text): bool
{
    return \Shared\Sanitize\InputSanitizer::containsUrl($text);
}

/**
 * @deprecated Use \Shared\Sanitize\InputSanitizer::stripUrls() directly.
 */
function sw_strip_urls(string $text): string
{
    return \Shared\Sanitize\InputSanitizer::stripUrls($text);
}

/**
 * @deprecated Use \Shared\Sanitize\InputSanitizer::sanitizeText() directly.
 */
function sw_sanitize_text_field(?string $input): string
{
    return \Shared\Sanitize\InputSanitizer::sanitizeText($input);
}

/**
 * @deprecated Use \Shared\Sanitize\InputSanitizer::sanitizeTextarea() directly.
 */
function sw_sanitize_textarea_field(?string $input): string
{
    return \Shared\Sanitize\InputSanitizer::sanitizeTextarea($input);
}

/**
 * Sanitize a CSS color value, accepting hex (#fff / #ffffff), rgb(), rgba() only.
 *
 * @param  mixed       $color Raw value from ACF or any source.
 * @return string|null        Validated color string, or null if not a valid color.
 */
function sw_sanitize_css_color(mixed $color): ?string
{
    if (!$color) {
        return null;
    }
    $color = trim((string) $color);

    // Hex (#fff or #ffffff)
    if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color)) {
        return $color;
    }

    // rgb() or rgba()
    if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $color, $m)) {
        if ($m[1] <= 255 && $m[2] <= 255 && $m[3] <= 255) {
            return $color;
        }
    }

    return null;
}

/**
 * Build an HTML srcset string from an ACF image array.
 *
 * Iterates over all registered WordPress image sizes present in the ACF
 * image's `sizes` sub-array, excluding the listed sizes (default: thumbnail).
 * Appends the full-size original as the largest candidate.
 *
 * @param  array    $image   ACF image array (return_format: array).
 * @param  string[] $exclude Size names to skip. Default: ['thumbnail'].
 * @return string            Comma-separated srcset string, or empty string.
 */
function sw_build_srcset(array $image, array $exclude = ['thumbnail']): string
{
    $known_sizes = ['thumbnail', 'medium', 'medium_large', 'large', '1536x1536', '2048x2048'];
    $parts = [];
    $seen_widths = [];

    foreach ($known_sizes as $size) {
        if (in_array($size, $exclude, true)) {
            continue;
        }
        $url = $image['sizes'][$size] ?? '';
        $w   = (int) ($image['sizes'][$size . '-width'] ?? 0);
        if ($url && $w && !isset($seen_widths[$w])) {
            $parts[]          = esc_url($url) . ' ' . $w . 'w';
            $seen_widths[$w]  = true;
        }
    }

    // Full size (original)
    $full_url = $image['url'] ?? '';
    $full_w   = (int) ($image['width'] ?? 0);
    if ($full_url && $full_w && !isset($seen_widths[$full_w])) {
        $parts[] = esc_url($full_url) . ' ' . $full_w . 'w';
    }

    return implode(', ', $parts);
}

/**
 * Parse a raw date string into a DateTimeImmutable, trying multiple formats.
 *
 * Uses createFromFormat() with strict error/warning checking to avoid silent
 * date overflow (e.g. "Feb 30" rolling into March without warning).
 *
 * @param  string|null $raw        Raw value, e.g. "20260218", "18/02/2026".
 * @param  string[]    $tryFormats Ordered candidate formats.
 * @return \DateTimeImmutable|null Parsed date, or null on any parse failure.
 */
function sw_parse_date(?string $raw, array $tryFormats = ['Ymd', 'd/m/Y', 'Y-m-d']): ?\DateTimeImmutable
{
    if (empty($raw)) {
        return null;
    }

    foreach ($tryFormats as $fmt) {
        $dt = \DateTimeImmutable::createFromFormat($fmt, $raw);
        if ($dt === false) {
            continue;
        }

        // getLastErrors() returns false on PHP 8.2+ clean parse, array on older versions.
        // Reject on warnings too - e.g. day-overflow silently rolls the date forward.
        $errors = \DateTimeImmutable::getLastErrors();
        if ($errors === false || ($errors['error_count'] === 0 && $errors['warning_count'] === 0)) {
            return $dt;
        }
    }

    return null;
}

/**
 * Format a raw date string for UI display.
 *
 * Uses wp_date() for the display string so the WP Settings → General timezone
 * is honoured for any format that includes a time component.
 *
 * @param  string|null $raw           Raw date string.
 * @param  string      $displayFormat PHP date format, e.g. 'd.m.Y'.
 * @param  bool        $returnBoth    If true, returns ['display' => '', 'iso' => ''].
 * @return string|array{display: string, iso: string}
 */
function sw_format_date_for_ui(?string $raw, string $displayFormat = 'd.m.Y', bool $returnBoth = false): string|array
{
    $dt = sw_parse_date($raw);

    if (!$dt) {
        return $returnBoth ? ['display' => '', 'iso' => ''] : '';
    }

    $display = wp_date($displayFormat, $dt->getTimestamp());
    $iso     = $dt->format('Y-m-d');

    return $returnBoth ? ['display' => $display, 'iso' => $iso] : $display;
}

/** ========================================================================
 *   DATA GETTERS - ACF & WP Wrappers
 *  ======================================================================== */

/**
 * Normalize ACF's get_field() "no value" false → null.
 *
 * ACF returns boolean false when a field has no saved value, which is
 * ambiguous alongside legitimate boolean fields. This wrapper makes every
 * absent field an explicit null.
 */
function sw_get_field(string $key, mixed $context = false): mixed
{
    $value = function_exists('get_field') ? get_field($key, $context) : null;
    return ($value !== false) ? $value : null;
}

/**
 * Get a term field value via ACF with a native get_term_meta() fallback.
 *
 * The $taxonomy parameter is kept for call-site clarity even though
 * get_term_meta() does not require it - it serves as self-documentation.
 */
function sw_get_term_field(string $key, int $term_id, string $taxonomy): mixed
{
    $context = "term_{$term_id}";
    return function_exists('get_field')
        ? get_field($key, $context)
        : get_term_meta($term_id, $key, true);
}

/**
 * Fetch term objects for an explicit list of IDs in a single query.
 *
 * More efficient than calling get_term() in a loop for multiple IDs.
 *
 * @param  int[]      $term_ids Term IDs to fetch.
 * @param  string     $taxonomy Taxonomy name.
 * @return \WP_Term[]           Validated term objects; empty array on any failure.
 */
function sw_get_prepared_terms(array $term_ids, string $taxonomy): array
{
    if (empty($term_ids) || !taxonomy_exists($taxonomy)) {
        return [];
    }

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'include'    => array_map('intval', $term_ids),
        'hide_empty' => false,
    ]);

    return (is_array($terms) && !is_wp_error($terms)) ? $terms : [];
}

/**
 * Resolve root (top-level) ancestor terms for a given post.
 *
 * Two queries total, regardless of taxonomy size or how many roots are found:
 *
 *   Query 1 — 'id=>parent': fetches the full parent map as int pairs. This is
 *   an actual SQL query (not the autoloaded _children option that get_ancestors()
 *   uses), but it returns only two integers per row so memory stays negligible
 *   even for tens of thousands of terms.
 *
 *   In-memory traversal — the parent map is walked in PHP with zero further DB
 *   calls to identify which term IDs are roots.
 *
 *   Query 2 — 'include' => $root_ids: hydrates only the K root terms into full
 *   WP_Term objects. K is typically 1–3 for a well-structured taxonomy.
 *
 * Why not get_ancestors()?
 *   get_ancestors() reads _get_term_hierarchy() which loads from an autoloaded
 *   WP option — so the hierarchy data itself costs no extra DB query. However,
 *   the approach that pairs it with get_term() per root still requires K DB calls
 *   for root hydration unless those specific terms happen to already be in the
 *   object cache. Our approach always costs exactly 2 queries and does not depend
 *   on cache warmth or internal WP option format.
 *
 * @param  int        $post_id  Post ID.
 * @param  string     $taxonomy Taxonomy name.
 * @return \WP_Term[]           Unique root WP_Term objects; empty on failure.
 */
function sw_get_root_terms(int $post_id, string $taxonomy): array
{
    $post_terms = get_the_terms($post_id, $taxonomy);
    if (empty($post_terms) || is_wp_error($post_terms)) {
        return [];
    }

    // Two integers per row — memory stays negligible even for large taxonomies
    $all_parents = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'fields'     => 'id=>parent',
    ]);

    if (is_wp_error($all_parents) || empty($all_parents)) {
        return [];
    }

    // Walk to root in memory — zero further DB calls
    $root_ids = [];
    foreach ($post_terms as $term) {
        $current_id = $term->term_id;

        while (isset($all_parents[$current_id]) && $all_parents[$current_id] !== 0) {
            $current_id = $all_parents[$current_id];
        }

        $root_ids[$current_id] = true;
    }

    if (empty($root_ids)) {
        return [];
    }

    // Single bulk query — hydrate only the root term objects we actually need
    $roots = get_terms([
        'taxonomy'   => $taxonomy,
        'include'    => array_keys($root_ids),
        'hide_empty' => false,
    ]);

    return (!is_wp_error($roots) && is_array($roots)) ? array_values($roots) : [];
}

/**
 * Map a MIME type to a short, display-friendly file-type label.
 * Returns null for unsupported types so the caller can decide what to render.
 */
function sw_document_type_label(string $mime): ?string
{
    return match ($mime) {
        'application/pdf'                                                         => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        default => null,
    };
}

/**
 * Normalize an ACF file field value into a consistent document array.
 *
 * Handles all ACF return formats: array (return_format=array),
 * int (return_format=id), null, or false (ACF "no value" sentinel).
 *
 * @param  array|int|bool|null $raw  ACF file field value.
 * @return array{url: string, title: string, filesize: int, type: string|null}|null
 */
function sw_prepare_document(mixed $raw): ?array
{
    if (empty($raw)) {
        return null; // handles false, null, 0, []
    }

    // ACF return_format = array — file data already deserialized by ACF
    if (is_array($raw)) {
        return [
            'url'      => $raw['url']             ?? '',
            'title'    => $raw['title']           ?? '',
            'filesize' => (int) ($raw['filesize'] ?? 0),
            'type'     => sw_document_type_label($raw['mime_type'] ?? ''),
        ];
    }

    // ACF return_format = id — resolve from attachment ID
    if (is_int($raw)) {
        $url = wp_get_attachment_url($raw);
        if (!$url) {
            return null;
        }

        // Two-tier filesize resolution:
        //
        // Tier 1 — wp_get_attachment_metadata(): for images uploaded under WP 6.0+
        //   WordPress stores 'filesize' directly in the attachment metadata array,
        //   which is already cached in the object cache after the first read.
        //   Zero filesystem I/O on a cache hit.
        //
        // Tier 2 — filesystem fallback: for PDFs and pre-6.0 images the metadata
        //   array has no 'filesize' key, so we fall back to a direct read.
        //   get_attached_file() returns false for remote-hosted files (S3 / offload
        //   plugins), so file_exists() guards against a network timeout on a
        //   non-local path.
        $filesize = 0;
        $meta     = wp_get_attachment_metadata($raw);

        if (isset($meta['filesize'])) {
            $filesize = (int) $meta['filesize'];
        } else {
            $path = get_attached_file($raw);
            if ($path && file_exists($path)) {
                $filesize = (int) filesize($path);
            }
        }

        return [
            'url'      => $url,
            'title'    => get_the_title($raw),
            'filesize' => $filesize,
            'type'     => sw_document_type_label((string) get_post_mime_type($raw)),
        ];
    }

    return null;
}

/** =========================================================================
 *   BUSINESS LOGIC - Opportunities
 *  ========================================================================= */

/**
 * One-time migration: Copy ACF opportunity_description to post_content.
 * 
 * Can be run via WP-CLI: wp eval 'sw_migrate_description_to_post_content();'
 * Or runs automatically via admin_init hook (once, tracked by option).
 * 
 * @return int Number of posts migrated.
 */
function sw_migrate_description_to_post_content(): int
{
    // Check if migration already completed
    if (get_option('sw_description_migration_done', false)) {
        return 0;
    }

    $posts = get_posts([
        'post_type'      => 'opportunity',
        'posts_per_page' => -1,
        'post_status'    => ['publish', 'draft', 'pending'],
    ]);

    $migrated = 0;
    foreach ($posts as $post) {
        // Skip if post_content already has content
        if (!empty(trim($post->post_content))) {
            continue;
        }

        $acf_description = get_post_meta($post->ID, 'opportunity_description', true);

        if (!empty($acf_description)) {
            $result = wp_update_post([
                'ID'           => $post->ID,
                'post_content' => $acf_description,
            ], true);

            if (!is_wp_error($result)) {
                $migrated++;
            }
        }
    }

    // Mark migration as completed
    update_option('sw_description_migration_done', true, false);

    if ($migrated > 0) {
        error_log("Opportunity description migration completed: {$migrated} posts migrated.");
    }

    return $migrated;
}

/**
 * Auto-run migration on admin_init (once).
 * Already done.
 */
/*
add_action('admin_init', function (): void {
    // Only run in admin context, and only if not already done
    if (!get_option('sw_description_migration_done', false)) {
        sw_migrate_description_to_post_content();
    }
});
*/

/**
 * One-time migration: Copy ACF fields to native WordPress fields for the News CPT.
 *
 * Migrations performed:
 *  - ACF `title`        → post_title
 *  - ACF `description`  → post_content (as Gutenberg paragraph blocks)
 *  - ACF `photo`        → post featured image (post thumbnail)
 *
 * Can be run via WP-CLI: wp eval 'sw_migrate_news_fields();'
 * Or uncomment the admin_init hook at the bottom to run automatically (once).
 *
 * @return array{ title: int, description: int, photo: int } Counts of migrated posts per field.
 */
function sw_migrate_news_fields(): array
{
    if (get_option('sw_news_migration_done', false)) {
        return ['title' => 0, 'description' => 0, 'photo' => 0];
    }

    $posts = get_posts([
        'post_type'      => 'news',
        'posts_per_page' => 500,
        'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
    ]);

    $migrated_title       = 0;
    $migrated_description = 0;
    $migrated_photo       = 0;

    foreach ($posts as $post) {

        // ── 1. title → post_title ─────────────────────────────────────────────
        // if (empty(trim($post->post_title))) {
        $acf_title = get_post_meta($post->ID, 'title', true);

        if (!empty($acf_title)) {
            $result = wp_update_post([
                'ID'         => $post->ID,
                'post_title' => sanitize_text_field($acf_title),
            ], true);

            if (!is_wp_error($result)) {
                $migrated_title++;
            }
        }
        // }

        // ── 2. description → post_content (Gutenberg paragraph blocks) ───────
        if (empty(trim($post->post_content))) {
            $acf_description = get_post_meta($post->ID, 'description', true);

            if (!empty($acf_description)) {
                // Treat as plain text: normalise line endings, split on blank
                // lines into paragraphs, convert single newlines to <br>.
                // wp_kses_post() sanitises before storage; serialize_block()
                // wraps each paragraph in proper Gutenberg block markup.
                $text       = str_replace("\r\n", "\n", trim($acf_description));
                $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);

                $blocks = array_map(static function (string $p): string {
                    $html = '<p>' . nl2br(wp_kses_post(trim($p)), false) . '</p>';
                    return serialize_block([
                        'blockName'    => 'core/paragraph',
                        'attrs'        => [],
                        'innerBlocks'  => [],
                        'innerHTML'    => $html,
                        'innerContent' => [$html],
                    ]);
                }, $paragraphs);

                $post_content = implode("\n\n", $blocks);

                if (!empty($post_content)) {
                    $result = wp_update_post([
                        'ID'           => $post->ID,
                        'post_content' => $post_content,
                    ], true);

                    if (!is_wp_error($result)) {
                        $migrated_description++;
                    }
                }
            }
        }

        // ── 3. photo → featured image (thumbnail) ────────────────────────────
        if (!has_post_thumbnail($post->ID)) {
            $acf_photo = get_post_meta($post->ID, 'photo', true);

            $attachment_id = 0;

            if (is_array($acf_photo) && !empty($acf_photo['ID'])) {
                // ACF stored the full image array (return_format: array)
                $attachment_id = (int) $acf_photo['ID'];
            } elseif (is_numeric($acf_photo) && (int) $acf_photo > 0) {
                // ACF stored the raw attachment ID
                $attachment_id = (int) $acf_photo;
            }

            if ($attachment_id > 0 && get_post($attachment_id)) {
                if (set_post_thumbnail($post->ID, $attachment_id)) {
                    $migrated_photo++;
                }
            }
        }
    }

    // Mark migration as completed only when no posts are left to process.
    // Re-run the script if output shows posts were migrated (batch of 500).
    if ($migrated_title === 0 && $migrated_description === 0 && $migrated_photo === 0) {
        update_option('sw_news_migration_done', true, false);
    }

    error_log(
        "News migration batch: " .
            "{$migrated_title} titles migrated, " .
            "{$migrated_description} descriptions migrated, " .
            "{$migrated_photo} featured images migrated."
    );

    return [
        'title'       => $migrated_title,
        'description' => $migrated_description,
        'photo'       => $migrated_photo,
    ];
}

/**
 * Auto-run migration on admin_init (once).
 * Uncomment when ready to deploy.
 */
/*
add_action('admin_init', function (): void {
    if (!get_option('sw_news_migration_done', false)) {
        sw_migrate_news_fields();
    }
});
*/

/**
 * Assemble view data for a single Opportunity post.
 *
 * Scalar meta fields use get_post_meta() directly — no ACF overhead for simple
 * strings. Complex relational fields (arrays, objects) use sw_get_field() for
 * ACF's deserialization. Cast to (string) makes the return contract explicit
 * and prevents nulls from propagating silently into templates.
 *
 * @param  int   $post_id
 * @return array
 */
function sw_get_opportunity_view_data(int $post_id): array
{
    global $wpdb;

    $locations = $wpdb->get_results($wpdb->prepare(
        "SELECT code, name_category_oblast as name, level, category
         FROM {$wpdb->prefix}v_opportunity_search
         WHERE post_id = %d
         ORDER BY level ASC, name ASC",
        $post_id
    ));

    $d_start = sw_format_date_for_ui(get_post_meta($post_id, 'opportunity_date_starts', true), 'd.m.y', true);
    $d_end   = sw_format_date_for_ui(get_post_meta($post_id, 'opportunity_date_ends',   true), 'd.m.y', true);

    // Country — typed storage replaces the `country` taxonomy. JOIN against
    // wp_sw_countries gets the display name in one round trip; LIMIT 1
    // matches the 1:1 invariant enforced at write time (setCountry()).
    $country_row = $wpdb->get_row($wpdb->prepare(
        "SELECT c.id, c.code, c.name
         FROM {$wpdb->prefix}sw_countries c
         INNER JOIN {$wpdb->prefix}opportunity_countries oc ON oc.country_id = c.id
         WHERE oc.post_id = %d
         LIMIT 1",
        $post_id
    ));
    $country_name = $country_row ? $country_row->name : __('Worldwide', 'starwishx');

    $seeker_ids   = sw_get_field('opportunity_seekers', $post_id) ?? [];
    $seeker_terms = sw_get_prepared_terms($seeker_ids, 'category-seekers');

    $post = get_post($post_id);

    return [
        // Scalar fields — get_post_meta() is cheaper than ACF for simple strings
        // 'applicant_name' => (string) get_post_meta($post_id, 'opportunity_applicant_name', true), // deprecated
        'company'          => (string) get_post_meta($post_id, 'opportunity_company',          true),
        'source_url'       => (string) get_post_meta($post_id, 'opportunity_sourcelink',       true),
        'application_form' => (string) get_post_meta($post_id, 'opportunity_application_form', true),
        // Now stored in post_content
        // 'description'      => $post ? $post->post_content : '', 
        // 'description' => apply_filters('the_content', $post->post_content),
        'description' => wpautop($post->post_content),

        'requirements'     => (string) get_post_meta($post_id, 'opportunity_requirements',     true),
        'details'          => (string) get_post_meta($post_id, 'opportunity_details',          true),
        // Complex / relational fields
        'country_name'     => $country_name,
        'seeker_terms'     => $seeker_terms,
        'document'         => sw_prepare_document(sw_get_field('opportunity_document', $post_id)),
        // Computed
        'locations'        => $locations,
        'root_categories'  => sw_get_root_terms($post_id, 'category-oportunities'),
        'date_start'       => $d_start,
        'date_end'         => $d_end,
    ];
}

/** =======================================================================
 *   VIEW HELPERS
 *  ======================================================================= */

/**
 * Render an HTML <time> element with a machine-readable datetime attribute.
 *
 * This element appears in any template that displays a date — policy pages,
 * event listings, post meta, opportunity deadlines. The two escaping calls
 * (esc_attr on the attribute, esc_html on the text node) must both be correct
 * every time. Extracting them here makes one guaranteed-safe implementation
 * instead of a scattered pattern where any instance can silently get it wrong.
 *
 * Composes naturally with sw_format_date_for_ui(..., returnBoth: true):
 *
 *   $date = sw_format_date_for_ui($raw, 'j F Y', true);
 *   if ($date['iso']) {
 *       echo esc_html__('Effective date:', THEME_TD) . ' ';
 *       echo sw_time_tag($date['iso'], $date['display']);
 *   }
 *
 * @param  string $iso     ISO 8601 string for the datetime attribute (e.g. "2026-02-18").
 * @param  string $display Human-readable text shown inside the element.
 * @return string          Safe <time> element markup.
 */
function sw_time_tag(string $iso, string $display): string
{
    return sprintf(
        '<time datetime="%s">%s</time>',
        esc_attr($iso),
        esc_html($display)
    );
}

/**
 * Echo an HTML <time> element.
 *
 * Thin wrapper around sw_time_tag() following the WP esc_html / esc_html_e convention.
 *
 * @see sw_time_tag() for full parameter documentation.
 */
function sw_time_tag_e(string $iso, string $display): void
{
    echo sw_time_tag($iso, $display); // phpcs:ignore WordPress.Security.EscapeOutput — escaped inside sw_time_tag()
}

/**
 * Generate CSS rules for top-level terms of a given taxonomy.
 *
 * Without a persistent object cache (Redis/Memcached) each call hits the DB
 * and ACF term meta on every page load. The output is entirely deterministic
 * from term meta, so it is stored as a transient and rebuilt only when term
 * data actually changes.
 *
 * Cache invalidation covers two paths:
 *   - Term record changes (admin form, REST, programmatic): 'edited_term' etc.
 *   - ACF field saves on a term: these write term meta directly and fire
 *     'updated_term_meta' / 'added_term_meta', NOT 'edited_term'. Hooking only
 *     the term lifecycle hooks would silently miss color updates made through
 *     the ACF field UI.
 *
 * The meta hooks fire for ALL taxonomies. Each call does a cached get_term()
 * to resolve the taxonomy before deciding whether to clear our transient, so
 * the overhead on unrelated term meta writes is one object-cache read.
 *
 * Usage:
 *   add_action('wp_head', function() {
 *       $css = sw_get_taxonomy_top_level_colors_styles();
 *       if ($css) echo "<style>{$css}</style>";
 *   });
 *
 * @param  string $taxonomy Taxonomy name.
 * @return string           CSS block; empty string if no terms or no colors defined.
 */
function sw_get_taxonomy_top_level_colors_styles(string $taxonomy = 'category-oportunities'): string
{
    $cache_key  = 'sw_styles_' . md5($taxonomy);
    $cached_css = get_transient($cache_key);

    if ($cached_css !== false) {
        return $cached_css;
    }

    if (!taxonomy_exists($taxonomy)) {
        return '';
    }

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'parent'     => 0,
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms) || empty($terms)) {
        return '';
    }

    $rules = [];

    foreach ($terms as $term) {
        $class = sanitize_html_class($term->slug);
        if ($class === '') {
            continue;
        }

        $bg     = sw_sanitize_css_color(sw_get_term_field('cat_opportunity_color_background', $term->term_id, $taxonomy));
        $text   = sw_sanitize_css_color(sw_get_term_field('cat_opportunity_color_text',       $term->term_id, $taxonomy));
        $border = sw_sanitize_css_color(sw_get_term_field('cat_opportunity_color_border',     $term->term_id, $taxonomy));

        if (!$bg && !$text && !$border) {
            continue;
        }

        $declarations = [];
        if ($bg)     $declarations[] = "background-color: {$bg}";
        if ($text)   $declarations[] = "color: {$text}";
        // if ($border) $declarations[] = "border-color: {$border}";
        if ($border) $declarations[] = "border: 1px solid {$border}";

        $rules[] = ".{$class}, [data-slug=\"{$class}\"] { " . implode('; ', $declarations) . '; }';
    }

    $css = implode("\n", $rules);
    set_transient($cache_key, $css, DAY_IN_SECONDS);

    return $css;
}

/**
 * Bust the taxonomy colors CSS transient cache.
 *
 * @internal Invoked by WordPress hooks — not intended for direct calls.
 */
function sw_clear_taxonomy_colors_cache(int $term_id, int $tt_id, string $taxonomy): void
{
    delete_transient('sw_styles_' . md5($taxonomy));
}

// Term record changes (admin term edit form, REST API, programmatic)
add_action('edited_term', 'sw_clear_taxonomy_colors_cache', 10, 3);
add_action('create_term', 'sw_clear_taxonomy_colors_cache', 10, 3);
add_action('delete_term', 'sw_clear_taxonomy_colors_cache', 10, 3);

// ACF field saves on a term write term meta directly — they do NOT fire edited_term
add_action('updated_term_meta', function (int $meta_id, int $term_id, string $meta_key): void {
    $term = get_term($term_id);
    if ($term && !is_wp_error($term)) {
        sw_clear_taxonomy_colors_cache($term_id, $term->term_taxonomy_id, $term->taxonomy);
    }
}, 10, 3);

add_action('added_term_meta', function (int $meta_id, int $term_id, string $meta_key): void {
    $term = get_term($term_id);
    if ($term && !is_wp_error($term)) {
        sw_clear_taxonomy_colors_cache($term_id, $term->term_taxonomy_id, $term->taxonomy);
    }
}, 10, 3);

/**
 * Render an SVG sprite icon.
 *
 * Covers the full range of real-world call sites with minimal noise:
 *
 *   sw_svg('icon-calendar')          → 18×18, decorative (aria-hidden)
 *   sw_svg('logo', 24)               → 24×24, decorative (#logo)
 *   sw_svg('arrow', 13, 16)          → 13×16, decorative (non-square)
 *   sw_svg('close', 20, null, 'red') → 20×20, class="red", decorative
 *   sw_svg('search', 18, null, '', 'Search') → 18×18, meaningful (aria-label)
 *
 * The icon argument expects the ID exactly as defined in the sprite file.
 * A '#' prefix is added automatically if omitted (e.g. 'logo' → '#logo').
 *
 * The sprite URL is derived from the active theme directory and exposed via the
 * 'sw_svg_sprite_url' filter for child themes or unusual asset layouts.
 *
 * @param  string      $icon   Icon ID (e.g. 'icon-calendar' or '#logo').
 * @param  int         $size   Width in px; also used as height unless $height is set.
 * @param  int|null    $height Height in px; pass for non-square icons.
 * @param  string      $class  CSS class names. Default is empty string (optional).
 * @param  string|null $label  Accessible label. Null → decorative (aria-hidden).
 *                             Non-null → meaningful icon (aria-label + role="img").
 * @return string              Safe SVG markup.
 */
function sw_svg(string $icon, int $size = 18, ?int $height = null, string $class = '', ?string $label = null): string
{
    // Ensure the fragment has a hash separator (e.g. 'calendar' → '#calendar')
    $fragment = str_starts_with($icon, '#') ? $icon : '#' . $icon;

    $sprite_url = apply_filters(
        'sw_svg_sprite_url',
        get_template_directory_uri() . '/assets/img/sprites.svg'
    );

    $href   = esc_attr($sprite_url . $fragment);
    $width  = $size;
    $height = $height ?? $size;

    // Build attributes
    // Logic: A class is decorative/visual, so empty string is sufficient default.
    $class_attr = $class !== '' ? sprintf('class="%s"', esc_attr($class)) : '';

    // Logic: Label determines accessibility tree visibility.
    $aria_attr = $label !== null
        ? sprintf('role="img" aria-label="%s"', esc_attr($label))
        : 'aria-hidden="true"';

    // wp_kses() with a strict allowlist — no raw HTML fragment variables echoed.
    // We explicitly allow 'class' on the SVG tag.
    return wp_kses(
        sprintf(
            '<svg width="%d" height="%d" %s %s focusable="false"><use href="%s"></use></svg>',
            $width,
            $height,
            $class_attr,
            $aria_attr,
            $href
        ),
        [
            'svg' => [
                'width'       => [],
                'height'      => [],
                'class'       => [],
                'aria-hidden' => [],
                'aria-label'  => [],
                'role'        => [],
                'focusable'   => [],
            ],
            'use' => ['href' => []],
        ]
    );
}

/**
 * Echo an SVG sprite icon.
 *
 * Thin wrapper around sw_svg() following the WP esc_html / esc_html_e convention.
 * Use in templates where you would otherwise write echo sw_svg(...).
 *
 * @see sw_svg() for full parameter documentation.
 */
function sw_svg_e(string $icon, int $size = 18, ?int $height = null, string $class = '', ?string $label = null): void
{
    echo sw_svg($icon, $size, $height, $class, $label); // phpcs:ignore WordPress.Security.EscapeOutput — escaped inside sw_svg()
}

/**
 * Render a tag list with optional collapsible "show more" and per-term slug classes.
 *
 * Uses native HTML <details>/<summary> for the collapse mechanic — zero JS required.
 *
 * @param  array  $items           Items: WP_Term objects, associative arrays with 'name', or plain strings.
 * @param  string $item_class      Base CSS class for each <li>.
 * @param  int    $visible_count   Items shown before collapsing. 0 = never collapse.
 * @param  string $container_class CSS class for the outer <ul>.
 * @param  bool   $add_term_slug   Append term->slug to each <li> class for WP_Term items.
 * @return string                  HTML output, or empty string if no renderable items.
 */
function sw_render_collapsible_tag_list(
    array $items,
    string $item_class,
    int $visible_count = 3,
    string $container_class = 'tag-list',
    bool $add_term_slug = false
): string {
    if (empty($items)) {
        return '';
    }

    $valid_items = array_values(array_filter($items, function (mixed $item): bool {
        $name = is_object($item)
            ? ($item->name ?? '')
            : (is_array($item) ? ($item['name'] ?? '') : trim((string) $item));
        return $name !== '';
    }));

    if (empty($valid_items)) {
        return '';
    }

    $render_item = function (mixed $item) use ($item_class, $add_term_slug): string {
        $name = is_object($item)
            ? ($item->name ?? '')
            : (is_array($item) ? ($item['name'] ?? '') : (string) $item);

        $slug_class = ($add_term_slug && is_object($item) && !empty($item->slug))
            ? ' ' . esc_attr($item->slug)
            : '';

        return sprintf(
            '<li class="%s">%s</li>',
            esc_attr($item_class . $slug_class),
            esc_html($name)
        );
    };

    $total = count($valid_items);

    if ($total <= $visible_count || $visible_count <= 0) {
        return sprintf(
            '<ul class="%s">%s</ul>',
            esc_attr($container_class),
            implode('', array_map($render_item, $valid_items))
        );
    }

    $visible   = array_slice($valid_items, 0, $visible_count);
    $hidden    = array_slice($valid_items, $visible_count);
    $remaining = count($hidden);

    // FIX — Unescaped translator output: __() / sprintf(_n()) result was passed
    // directly into the sprintf() HTML template. A compromised translation file
    // could inject arbitrary markup into <summary>. Applied esc_html().
    $summary_text = esc_html(
        $remaining === 1
            ? __('Show 1 more', 'starwishx')
            : sprintf(_n('Show %d more', 'Show %d more', $remaining, 'starwishx'), $remaining)
    );

    return sprintf(
        '<ul class="%1$s">%2$s'
            . '<li class="%1$s__more-item">'
            . '<details class="%1$s__details">'
            . '<summary class="%1$s__summary">%3$s</summary>'
            . '<ul class="%1$s %1$s__hidden">%4$s</ul>'
            . '</details></li></ul>',
        esc_attr($container_class),
        implode('', array_map($render_item, $visible)),
        $summary_text, // already escaped above
        implode('', array_map($render_item, $hidden))
    );
}
