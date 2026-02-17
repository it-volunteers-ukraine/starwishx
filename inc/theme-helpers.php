<?php

/** 
 * Helpers functions
 * 
 * File: inc/theme-helpers.php
 */

declare(strict_types=1);

if (!defined('ABSPATH')) exit;

/**
 * Check if string contains anything looking like a URL/Domain
 * dot inclide. more aggressive against bots.
 */
function sw_contains_url(string $text): bool
{
    // Checks for http/https OR for strings like "example.com" or "bit.ly"
    return (bool) preg_match('#(https?://|www\.)#i', $text) ||
        (bool) preg_match('#\b[a-z0-0-]+(\.[a-z]{2,})+(/\S*)?\b#i', $text);
    //todo check this:
    // (bool) preg_match('#\b[a-z0-9-]+(\.[a-z]{2,})+(/\S*)?\b#i', $text);
}

function sw_strip_urls(string $text): string
{
    if (! is_string($text) || trim($text) === '') {
        return '';
    }

    // Remove full protocol URLs (http/https/ftp/sftp/ftps + path/query/fragment/port/auth)
    $text = preg_replace('#\b(?:https?|ftp|sftp|ftps)://[^\s<>"\']+#i', '', $text);

    // Remove www. domains (requires at least one dot after www + TLD-like structure)
    $text = preg_replace('#\bwww\.[^\s<>"\']+\.[^\s<>"\']+#i', '', $text);

    // "The Dot Breaker" 
    // This finds a dot between two letters/numbers that DOES NOT have a space after it.
    // Example: bit.ly -> bit. ly
    // It ignores numbers like 10.5 by checking for letters on the right side.
    $text = preg_replace('/([a-z0-9])\.([a-z]{2,})/i', '$1. $2', $text);

    return $text;
}

function sw_sanitize_text_field(string $input): string
{
    if (! is_string($input)) {
        return '';
    }

    $text = sanitize_text_field($input);

    $text = sw_strip_urls($text);

    // Normalize whitespace (multiple spaces/tabs → single space) and trim
    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}

function sw_sanitize_textarea_field(string $input): string
{
    if (! is_string($input)) {
        return '';
    }

    $text = sanitize_textarea_field($input);

    $text = sw_strip_urls($text);

    // Optional light cleanup: normalize horizontal whitespace only (no touching newlines)
    // This prevents "   " becoming one space inside paragraphs but keeps line breaks intact
    $text = preg_replace('/[ \t]+/', ' ', $text);

    return trim($text);
}

/**
 * Sanitize a CSS color value (hex, rgb(), rgba() only).
 */
function sw_sanitize_css_color($color): ?string
{
    if (! $color) {
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
 * Get term meta with ACF fallback.
 */
function sw_get_term_field(string $key, int $term_id, string $taxonomy): mixed
{
    $context = "term_{$term_id}";
    return function_exists('get_field')
        ? get_field($key, $context)
        : get_term_meta($term_id, $key, true);
}

/**
 * Generate CSS rules for top‑level terms of a given taxonomy.
 * Usage: add_action( 'wp_head', 'get_taxonomy_top_level_colors_styles' );
 * @param string $taxonomy Taxonomy name. Default 'category-oportunities'.
 * @return string CSS rules, empty string if no terms or no colors defined.
 */
function get_taxonomy_top_level_colors_styles(string $taxonomy = 'category-oportunities'): string
{
    if (! taxonomy_exists($taxonomy)) {
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
        if ('' === $class) {
            continue;
        }

        $bg     = sw_sanitize_css_color(sw_get_term_field('cat_opportunity_color_background', $term->term_id, $taxonomy));
        $text   = sw_sanitize_css_color(sw_get_term_field('cat_opportunity_color_text', $term->term_id, $taxonomy));
        $border = sw_sanitize_css_color(sw_get_term_field('cat_opportunity_color_border', $term->term_id, $taxonomy));

        $bg     = sw_sanitize_css_color($bg);
        $text   = sw_sanitize_css_color($text);
        $border = sw_sanitize_css_color($border);

        if (! $bg && ! $text && ! $border) {
            continue;
        }

        $declarations = [];
        if ($bg) {
            $declarations[] = "background-color: {$bg}";
        }
        if ($text) {
            $declarations[] = "color: {$text}";
        }
        if ($border) {
            $declarations[] = "border-color: {$border}";
        }

        $rules[] = ".{$class} { " . implode('; ', $declarations) . '; }';
    }

    return implode("\n", $rules);
}

function sw_parse_date(?string $raw, array $tryFormats = ['Ymd', 'd/m/Y', 'Y-m-d']): ?\DateTimeImmutable
{
    if (empty($raw)) {
        return null;
    }

    foreach ($tryFormats as $fmt) {
        $dt = \DateTimeImmutable::createFromFormat($fmt, $raw);
        if ($dt !== false) {
            $errors = \DateTimeImmutable::getLastErrors();

            if (
                $errors === false ||
                ($errors['error_count'] === 0 && $errors['warning_count'] === 0)
            ) {
                return $dt;
            }
        }
    }
    return null;
}

/**
 * Format date for UI; can return string or both formats.
 *
 * @param string|null $raw
 * @param string $displayFormat PHP date format for display, e.g. 'd.m.Y' or 'd.m.y'
 * @param bool $returnBoth if true, returns array ['display'=>'', 'iso'=>'']
 * @return string|array
 */
function sw_format_date_for_ui(?string $raw, string $displayFormat = 'd.m.Y', bool $returnBoth = false): string|array
{
    $dt = sw_parse_date($raw);

    if (! $dt) {
        return $returnBoth ? ['display' => '', 'iso' => ''] : '';
    }

    $display = $dt->format($displayFormat);
    $iso     = $dt->format('Y-m-d');

    return $returnBoth ? ['display' => $display, 'iso' => $iso] : $display;
}

/**
 * Gets Opportunity View data
 */
function sw_get_opportunity_view_data(int $post_id): array
{
    global $wpdb;

    //? Note: call ListingService::getLocations($post_id) to avoid code duplication
    $locations = $wpdb->get_results($wpdb->prepare(
        "SELECT code, name_category_oblast as name, level, category 
         FROM {$wpdb->prefix}v_opportunity_search 
         WHERE post_id = %d
         ORDER BY level ASC, name ASC",
        $post_id
    ));

    // Dates - specific handling for ACF stored format vs UI format
    $raw_start = get_post_meta($post_id, 'opportunity_date_starts', true);
    $raw_end   = get_post_meta($post_id, 'opportunity_date_ends', true);

    $d_start   = sw_format_date_for_ui($raw_start, 'd.m.y', true);
    $d_end     = sw_format_date_for_ui($raw_end,   'd.m.y', true);
    // error_log(print_r($d_start) . print_r($raw_start));

    $country_id   = get_field('country', $post_id);
    $country_name = $country_id ? get_term($country_id, 'country')?->name : __('Worldwide', 'starwishx');

    $seeker_ids = get_field('opportunity_seekers', $post_id) ?: [];
    $seeker_terms = sw_get_prepared_terms($seeker_ids, 'category-seekers');

    $raw_document = get_field('opportunity_document', $post_id);

    // Return clean data object
    return [
        // Simple text fields -> Use get_post_meta (Faster)
        'applicant_name'  => get_post_meta($post_id, 'opportunity_applicant_name', true),
        'company'         => get_post_meta($post_id, 'opportunity_company', true),
        //? city is deprecared now
        // 'city'            => get_post_meta($post_id, 'city', true),
        'source_url'      => get_post_meta($post_id, 'opportunity_sourcelink', true),
        'description'     => get_post_meta($post_id, 'opportunity_description', true),
        'requirements'    => get_post_meta($post_id, 'opportunity_requirements', true),
        'details'         => get_post_meta($post_id, 'opportunity_details', true),

        // Complex fields -> Use get_field (Easier handling of arrays/objects)
        // 'country_id'      => $country_id, //? not sure its even needed now
        'country_name'    => $country_name,
        // 'seeker_ids'      => $seeker_ids, //? not sure its even needed now
        'seeker_terms'    => $seeker_terms,
        'document'        => sw_prepare_document(sw_get_field('opportunity_document', $post_id)),

        // Calculated Data
        'locations'       => $locations,
        'root_categories' => sw_get_root_terms($post_id, 'category-oportunities'),
        'date_start'      => $d_start,
        'date_end'        => $d_end,
    ];
}

// Normalize ACF false -> null globally
function sw_get_field(string $key, mixed $context = false): mixed
{
    $value = function_exists('get_field') ? get_field($key, $context) : null;
    return $value !== false ? $value : null;
}

/**
 * Retrieve and validate WordPress term objects by their IDs.
 * 
 * Fetches term objects in a single database query and filters out any invalid results.
 * More efficient than calling get_term() in a loop for multiple IDs.
 * 
 * @param array $term_ids Array of term IDs to fetch
 * @param string $taxonomy Taxonomy name (e.g., 'category', 'post_tag')
 * @return array Array of WP_Term objects, indexed by term_id. Empty if no valid terms.
 * 
 * @example
 * $terms = sw_get_prepared_terms([1, 2, 3], 'category');
 * foreach ($terms as $term) {
 *     echo $term->name;
 * }
 */
function sw_get_prepared_terms(array $term_ids, string $taxonomy): array
{
    if (empty($term_ids) || !taxonomy_exists($taxonomy)) {
        return [];
    }

    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'include' => array_map('intval', $term_ids), // Sanitize IDs
        'hide_empty' => false,
    ]);

    return (is_array($terms) && !is_wp_error($terms)) ? $terms : [];
}

/**
 * Resolves root (top-level) ancestor terms for a given post.
 * 
 * Fetches the entire taxonomy tree in a single query, then traverses
 * parent relationships in memory to avoid N+1 database calls.
 *
 * @param int    $post_id  Post ID
 * @param string $taxonomy Taxonomy name
 * @return WP_Term[] Array of unique root WP_Term objects
 */
function sw_get_root_terms(int $post_id, string $taxonomy): array
{
    $post_terms = get_the_terms($post_id, $taxonomy);
    if (empty($post_terms) || is_wp_error($post_terms)) {
        return [];
    }

    // Single query - fetch ALL terms of this taxonomy with parent data
    $all_terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'fields'     => 'all', // We need parent IDs
    ]);

    if (is_wp_error($all_terms) || empty($all_terms)) {
        return [];
    }

    // Build lookup map: term_id => WP_Term (pure memory traversal from here)
    $term_map = array_column($all_terms, null, 'term_id');

    // Climb to root using map, no DB calls
    $root_categories = [];
    foreach ($post_terms as $term) {
        $current = $term;

        while ($current->parent !== 0) {
            $parent = $term_map[$current->parent] ?? null;
            if (!$parent) break;
            $current = $parent;
        }

        $root_categories[$current->term_id] = $current;
    }

    return array_values($root_categories);
}

/**
 * Normalizes an ACF file field value into a consistent document array.
 * Handles all ACF return formats: array, int (ID), null, or false (ACF no-value).
 *
 * @param array|int|bool|null $raw ACF file field value
 * @return array{url: string, title: string, filesize: int}|null
 */
function sw_prepare_document(mixed $raw): ?array
{
    if (empty($raw)) return null; // handles false, null, 0, []

    if (is_array($raw)) {
        return [
            'url'      => $raw['url'] ?? '',
            'title'    => $raw['title'] ?? '',
            'filesize' => (int)($raw['filesize'] ?? 0),
        ];
    }

    if (is_int($raw)) {
        $url = wp_get_attachment_url($raw);
        if (!$url) return null;

        return [
            'url'      => $url,
            'title'    => get_the_title($raw),
            'filesize' => (int)(get_post_meta($raw, '_wp_attachment_metadata', true)['filesize'] ?? 0),
        ];
    }

    return null;
}

/**
 * Render a tag list with optional collapsible "show more" and per-term slug classes.
 * 
 * @param array  $items           Array of items (term objects, arrays, or strings)
 * @param string $item_class      Base CSS class for each <li> (e.g., 'tag-seekers')
 * @param int    $visible_count   Number of items to show before collapsing (use high value for no collapse)
 * @param string $container_class CSS class for the outer <ul> (default 'tag-list')
 * @param bool   $add_term_slug   If true, append term->slug to item class for WP_Term objects
 * @return string HTML output or empty string if no valid items
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

    // Extract and validate items, keeping originals for slug access
    $valid_items = [];
    foreach ($items as $item) {
        $name = is_object($item) ? ($item->name ?? '') : (is_array($item) ? ($item['name'] ?? '') : trim((string) $item));

        if ($name !== '') {
            $valid_items[] = $item; // Keep original (with potential ->slug)
        }
    }

    if (empty($valid_items)) {
        return '';
    }

    $total = count($valid_items);

    // Helper to render a single <li>
    $render_item = function ($item) use ($item_class, $add_term_slug) {
        $name = is_object($item) ? ($item->name ?? '') : (is_array($item) ? ($item['name'] ?? '') : (string) $item);

        $extra_class = '';
        if ($add_term_slug && is_object($item) && !empty($item->slug)) {
            $extra_class = ' ' . esc_attr($item->slug);
        }

        return sprintf(
            '<li class="%s">%s</li>',
            esc_attr($item_class . $extra_class),
            esc_html($name)
        );
    };

    // Early return: no collapsing needed
    if ($total <= $visible_count || $visible_count <= 0) {
        $all_html = implode('', array_map($render_item, $valid_items));

        return sprintf(
            '<ul class="%s">%s</ul>',
            esc_attr($container_class),
            $all_html
        );
    }

    // Collapsing needed
    $visible_items = array_slice($valid_items, 0, $visible_count);
    $hidden_items  = array_slice($valid_items, $visible_count);
    $remaining     = count($hidden_items);

    $visible_html = implode('', array_map($render_item, $visible_items));
    $hidden_html  = implode('', array_map($render_item, $hidden_items));

    // Use improved classes from previous review (optional but recommended)
    $more_item_class   = $container_class . '__more-item';
    $details_class     = $container_class . '__details';
    $summary_class     = $container_class . '__summary';
    $hidden_list_class = $container_class . ' ' . $container_class . '__hidden';

    $summary_text = $remaining === 1
        ? __('Show 1 more', 'starwishx')
        : sprintf(_n('Show %d more', 'Show %d more', $remaining, 'starwishx'), $remaining);

    return sprintf(
        '<ul class="%s">%s<li class="%s"><details class="%s"><summary class="%s">%s</summary><ul class="%s">%s</ul></details></li></ul>',
        esc_attr($container_class),
        $visible_html,
        esc_attr($more_item_class),
        esc_attr($details_class),
        esc_attr($summary_class),
        $summary_text,
        esc_attr($hidden_list_class),
        $hidden_html
    );
}
