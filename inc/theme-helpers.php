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


// --- Usage: add_action( 'wp_head', 'get_taxonomy_top_level_colors_styles' ); ---
/**
 * Sanitize a CSS color value (hex, rgb(), rgba() only).
 */
function _sanitize_css_color($color)
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
    if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $color)) {
        return $color;
    }

    return null;
}

/**
 * Generate CSS rules for top‑level terms of a given taxonomy.
 *
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

        // Get colors – ACF first, then fallback to term meta
        $context = "term_{$term->term_id}";
        if (function_exists('get_field')) {
            $bg     = get_field('cat_opportunity_color_background', $context);
            $text   = get_field('cat_opportunity_color_text', $context);
            $border = get_field('cat_opportunity_color_border', $context);
        } else {
            $bg     = get_term_meta($term->term_id, 'cat_opportunity_color_background', true);
            $text   = get_term_meta($term->term_id, 'cat_opportunity_color_text', true);
            $border = get_term_meta($term->term_id, 'cat_opportunity_color_border', true);
        }

        $bg     = _sanitize_css_color($bg);
        $text   = _sanitize_css_color($text);
        $border = _sanitize_css_color($border);

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
         FROM wp_v_opportunity_search 
         WHERE post_id = %d
         ORDER BY level ASC, name ASC",
        $post_id
    ));

    // Fetch Root Categories
    $root_categories = [];
    $terms = get_the_terms($post_id, 'category-oportunities');
    if (!empty($terms) && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $current = $term;
            // Recursive climb to parent
            while ($current->parent !== 0) {
                $parent = get_term($current->parent, 'category-oportunities');
                if (!$parent || is_wp_error($parent)) break;
                $current = $parent;
            }
            $root_categories[$current->term_id] = $current;
        }
    }

    // Dates - specific handling for ACF stored format vs UI format
    $raw_start = get_post_meta($post_id, 'opportunity_date_starts', true);
    $raw_end   = get_post_meta($post_id, 'opportunity_date_ends', true);

    $d_start   = sw_format_date_for_ui($raw_start, 'd.m.y', true);
    $d_end     = sw_format_date_for_ui($raw_end,   'd.m.y', true);
    // error_log(print_r($d_start) . print_r($raw_start));

    $country_id   = get_field('country', $post_id);
    $country_name = $country_id ? get_term($country_id, 'country')?->name : __('Worldwide', 'starwishx');

    // Return clean data object
    return [
        // Simple text fields -> Use get_post_meta (Faster)
        'applicant_name'  => get_post_meta($post_id, 'opportunity_applicant_name', true),
        'company'         => get_post_meta($post_id, 'opportunity_company', true),
        //!city is deprecared
        // 'city'            => get_post_meta($post_id, 'city', true),
        'source_url'      => get_post_meta($post_id, 'opportunity_sourcelink', true),
        'description'     => get_post_meta($post_id, 'opportunity_description', true),
        'requirements'    => get_post_meta($post_id, 'opportunity_requirements', true),
        'details'         => get_post_meta($post_id, 'opportunity_details', true),

        // Complex fields -> Use get_field (Easier handling of arrays/objects)
        'country_id'      => $country_id,
        'country_name'    => $country_name,
        'seeker_ids'      => get_field('opportunity_seekers', $post_id),
        'document'        => get_field('opportunity_document', $post_id),

        // Calculated Data
        'locations'       => $locations,
        'root_categories' => $root_categories,
        'date_start'      => $d_start,
        'date_end'        => $d_end,
    ];
}
