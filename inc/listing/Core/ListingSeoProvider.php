<?php
// File: inc/listing/Core/ListingSeoProvider.php
declare(strict_types=1);

namespace Listing\Core;

/**
 * Dynamic SEO meta for the Opportunities archive.
 *
 * Context-aware generation:
 *   /opportunities/              -> CPT description, theme screenshot
 *   /opportunities/{slug}/       -> term description, term ACF image
 *   /opportunities/?category=s   -> canonical -> /opportunities/{slug}/
 *
 * Integrates with Rank Math when present; falls back to raw meta in wp_head.
 */
class ListingSeoProvider
{
    private ?\WP_Term $categoryTerm = null;
    private string $cptDescription  = '';
    private bool $hasRankMath       = false;
    private bool $hasPrettyUrls     = false;

    public function register(): void
    {
        add_action('template_redirect', [$this, 'init']);
    }

    /**
     * Detect archive context and attach the appropriate hooks.
     * Runs at template_redirect — all query vars are parsed by this point.
     */
    public function init(): void
    {
        if (!is_post_type_archive('opportunity')) {
            return;
        }

        $this->hasPrettyUrls = defined('LISTING_PRETTY_CATEGORY_URLS') && LISTING_PRETTY_CATEGORY_URLS;
        $this->hasRankMath   = defined('RANK_MATH_VERSION');

        $cpt = get_post_type_object('opportunity');
        $this->cptDescription = $cpt->description ?? '';

        // Resolve category from pretty URL rewrite (listing_cat query var)
        $slug = get_query_var('listing_cat');
        if ($slug) {
            $term = get_term_by('slug', $slug, 'category-oportunities');
            if ($term && !is_wp_error($term)) {
                $this->categoryTerm = $term;
            }
        }

        if ($this->hasRankMath) {
            // Rank Math owns the HTML — we filter its values
            add_filter('rank_math/frontend/title',       [$this, 'filterRankMathTitle']);
            add_filter('rank_math/frontend/description', [$this, 'getDescription']);
            add_filter('rank_math/frontend/canonical',   [$this, 'getCanonical']);
            // Rank Math may not have og:image for archives — supplement
            add_action('wp_head', [$this, 'renderOgImage'], 40);
        } else {
            // No SEO plugin — we own title, description, OG, canonical
            add_filter('document_title_parts', [$this, 'filterTitleParts']);
            remove_action('wp_head', 'rel_canonical');
            add_action('wp_head', [$this, 'renderMeta'], 1);
        }
    }

    // ── Title ────────────────────────────────────────────────────────

    /**
     * Core WP title filter (no-Rank-Math path).
     * WP appends " - Site Name" automatically.
     */
    public function filterTitleParts(array $parts): array
    {
        if ($this->categoryTerm) {
            $parts['title'] = sprintf(
                '%s — %s',
                $this->categoryTerm->name,
                __('Opportunities', 'starwishx')
            );
        }
        return $parts;
    }

    /**
     * Rank Math title filter.
     * RM bypasses document_title_parts via pre_get_document_title at priority 15.
     * We filter RM's computed title, preserving its separator + site name suffix.
     */
    public function filterRankMathTitle(string $title): string
    {
        if (!$this->categoryTerm) {
            return $title;
        }

        $custom = sprintf(
            '%s — %s',
            $this->categoryTerm->name,
            __('Opportunities', 'starwishx')
        );

        // Preserve Rank Math's "SEP Site Name" suffix
        foreach ([' - ', ' | ', ' — ', ' – '] as $sep) {
            $pos = mb_strrpos($title, $sep);
            if ($pos !== false) {
                return $custom . mb_substr($title, $pos);
            }
        }

        return $custom;
    }

    // ── Description ──────────────────────────────────────────────────

    /**
     * Meta description: term description for categories, CPT description otherwise.
     */
    public function getDescription(string $description = ''): string
    {
        if ($this->categoryTerm && $this->categoryTerm->description) {
            return wp_trim_words(
                wp_strip_all_tags($this->categoryTerm->description),
                30,
                '…'
            );
        }

        return $this->cptDescription ?: $description;
    }

    // ── Canonical ────────────────────────────────────────────────────

    /**
     * Canonical URL strategy:
     *   Pretty URL with category  -> canonical is itself
     *   ?category=slug (single)   -> canonical -> pretty URL
     *   Multi-filter / default    -> no override
     */
    public function getCanonical(string $canonical = ''): string
    {
        if (!$this->hasPrettyUrls) {
            return $canonical;
        }

        // Pretty URL — canonical is itself
        if ($this->categoryTerm) {
            return $this->buildCategoryUrl($this->categoryTerm->slug);
        }

        // Query-param: single non-numeric slug with no other listing filters
        $catParam = sanitize_text_field(wp_unslash($_GET['category'] ?? ''));
        if ($catParam && !is_numeric($catParam) && $this->isOnlyCategoryFilter()) {
            $term = get_term_by('slug', sanitize_title($catParam), 'category-oportunities');
            if ($term && !is_wp_error($term)) {
                return $this->buildCategoryUrl($term->slug);
            }
        }

        return $canonical ?: (string) get_post_type_archive_link('opportunity');
    }

    // ── OG Image ─────────────────────────────────────────────────────

    /**
     * Category ACF image when available, theme screenshot as fallback.
     */
    public function getOgImageUrl(): string
    {
        if ($this->categoryTerm && function_exists('get_field')) {
            $image = get_field(
                'cat_opportunity_image',
                $this->categoryTerm->taxonomy . '_' . $this->categoryTerm->term_id
            );
            if (!empty($image)) {
                return $image['sizes']['medium_large'] ?? $image['url'] ?? '';
            }
        }

        return get_template_directory_uri() . '/screenshot.png';
    }

    // ── Renderers ────────────────────────────────────────────────────

    /**
     * Full meta output when no SEO plugin is active.
     */
    public function renderMeta(): void
    {
        $desc      = $this->getDescription();
        $canonical = $this->getCanonical((string) get_post_type_archive_link('opportunity'));
        $image     = $this->getOgImageUrl();
        $ogTitle   = $this->getOgTitle();

        if ($desc) {
            printf('<meta name="description" content="%s" />' . "\n", esc_attr($desc));
        }

        echo '<meta property="og:type" content="website" />' . "\n";
        printf('<meta property="og:title" content="%s" />' . "\n", esc_attr($ogTitle));
        if ($desc) {
            printf('<meta property="og:description" content="%s" />' . "\n", esc_attr($desc));
        }
        if ($image) {
            printf('<meta property="og:image" content="%s" />' . "\n", esc_url($image));
        }
        printf('<meta property="og:url" content="%s" />' . "\n", esc_url($canonical));
        printf('<meta property="og:site_name" content="%s" />' . "\n", esc_attr(get_bloginfo('name')));
        printf('<link rel="canonical" href="%s" />' . "\n", esc_url($canonical));
    }

    /**
     * OG image supplement for Rank Math (which handles other OG tags).
     * Multiple og:image tags are valid per the Open Graph spec.
     */
    public function renderOgImage(): void
    {
        $image = $this->getOgImageUrl();
        if ($image) {
            printf('<meta property="og:image" content="%s" />' . "\n", esc_url($image));
        }
    }

    // ── Internals ────────────────────────────────────────────────────

    private function getOgTitle(): string
    {
        if ($this->categoryTerm) {
            return sprintf(
                '%s — %s',
                $this->categoryTerm->name,
                __('Opportunities', 'starwishx')
            );
        }
        return __('Opportunities', 'starwishx');
    }

    private function buildCategoryUrl(string $slug): string
    {
        $archive = get_post_type_archive_link('opportunity');
        return $archive
            ? trailingslashit($archive . $slug)
            : home_url('/opportunities/' . $slug . '/');
    }

    /**
     * True when ?category= is the only listing-related query param.
     * Multi-filter URLs get no canonical override (they're faceted navigation).
     */
    private function isOnlyCategoryFilter(): bool
    {
        foreach (['seekers', 'country', 'location', 's'] as $key) {
            if (!empty($_GET[$key])) {
                return false;
            }
        }
        return true;
    }
}
