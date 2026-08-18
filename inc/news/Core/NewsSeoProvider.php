<?php

/**
 * SEO meta for the news category archive.
 *
 * /news/{category}/ is a post type archive as far as WordPress — and Rank
 * Math — are concerned, so without this every category page canonicalises to
 * /news/ and advertises /news/page/2/ as its next page. That would hide the
 * new URLs from search engines, which is the whole point of introducing them.
 *
 * Mirrors inc/listing/Core/ListingSeoProvider.php, minus the facet handling
 * the Listing archive needs.
 *
 * File: inc/news/Core/NewsSeoProvider.php
 */

declare(strict_types=1);

namespace News\Core;

use WP_Term;

final class NewsSeoProvider
{
    private ?WP_Term $term = null;
    private bool $resolved = false;

    /**
     * Rank Math filters are attached immediately, not at template_redirect:
     * it builds its canonical from Paper, which is generated earlier than
     * template_redirect, so a late-attached filter never runs.
     * Each callback checks the context itself.
     */
    public function register(): void
    {
        if (defined('RANK_MATH_VERSION')) {
            add_filter('rank_math/frontend/title',       [$this, 'filterRankMathTitle']);
            add_filter('rank_math/frontend/description', [$this, 'getDescription']);
            add_filter('rank_math/frontend/canonical',   [$this, 'getCanonical']);
            // Rank Math derives rel prev/next from its *unpaged* canonical,
            // which is computed before the canonical filter runs — its links
            // would still point at /news/page/N/. Emit our own instead.
            add_filter('rank_math/frontend/disable_adjacent_rel_links', [$this, 'maybeDisableAdjacentLinks']);
            add_action('wp_head', [$this, 'renderAdjacentLinks'], 22);

            return;
        }

        add_action('template_redirect', [$this, 'initFallback']);
    }

    /**
     * No SEO plugin — the theme owns title, description, OG and canonical.
     */
    public function initFallback(): void
    {
        if (!$this->term()) {
            return;
        }

        add_filter('document_title_parts', [$this, 'filterTitleParts']);
        remove_action('wp_head', 'rel_canonical');
        add_action('wp_head', [$this, 'renderMeta'], 1);
    }

    public function maybeDisableAdjacentLinks(bool $disable): bool
    {
        return $this->term() ? true : $disable;
    }

    /**
     * Current category term, or null when this is not the category archive.
     * Resolved lazily — the query is not parsed when register() runs.
     */
    private function term(): ?WP_Term
    {
        if ($this->resolved) {
            return $this->term;
        }

        // Before the main query is parsed there is nothing to resolve, and
        // caching a null now would poison every later call.
        if (!did_action('wp')) {
            return null;
        }

        $this->resolved = true;

        if (!is_post_type_archive(NewsCore::POST_TYPE)) {
            return null;
        }

        $slug = (string) get_query_var(NewsCore::QUERY_VAR);

        if ($slug === '') {
            return null;
        }

        $term = get_term_by('slug', $slug, NewsCore::TAXONOMY);

        if ($term && !is_wp_error($term)) {
            $this->term = $term;
        }

        return $this->term;
    }

    // ── Title ────────────────────────────────────────────────────────

    public function filterTitleParts(array $parts): array
    {
        $parts['title'] = $this->buildTitle();

        return $parts;
    }

    /**
     * Rank Math computes its own title; keep its " SEP Site Name" suffix.
     */
    public function filterRankMathTitle(string $title): string
    {
        if (!$this->term()) {
            return $title;
        }

        $custom = $this->buildTitle();

        foreach ([' - ', ' | ', ' — ', ' – '] as $sep) {
            $pos = mb_strrpos($title, $sep);

            if ($pos !== false) {
                return $custom . mb_substr($title, $pos);
            }
        }

        return $custom;
    }

    // ── Description ──────────────────────────────────────────────────

    public function getDescription(string $description = ''): string
    {
        $term = $this->term();

        if (!$term) {
            return $description;
        }

        if ($term->description) {
            return wp_trim_words(wp_strip_all_tags($term->description), 30, '…');
        }

        $cpt = get_post_type_object(NewsCore::POST_TYPE);

        return $cpt->description ?: $description;
    }

    // ── Canonical ────────────────────────────────────────────────────

    public function getCanonical(string $canonical = ''): string
    {
        if (!$this->term()) {
            return $canonical;
        }

        return $this->categoryUrl($this->currentPage());
    }

    // ── Renderers ────────────────────────────────────────────────────

    public function renderAdjacentLinks(): void
    {
        if (!$this->term()) {
            return;
        }

        $page  = $this->currentPage();
        $total = (int) ($GLOBALS['wp_query']->max_num_pages ?? 1);

        if ($page > 1) {
            printf('<link rel="prev" href="%s" />' . "\n", esc_url($this->categoryUrl($page - 1)));
        }

        if ($page < $total) {
            printf('<link rel="next" href="%s" />' . "\n", esc_url($this->categoryUrl($page + 1)));
        }
    }

    /**
     * Full meta output when no SEO plugin is active.
     */
    public function renderMeta(): void
    {
        $description = $this->getDescription();
        $canonical   = $this->getCanonical();
        $title       = $this->buildTitle();

        if ($description) {
            printf('<meta name="description" content="%s" />' . "\n", esc_attr($description));
        }

        echo '<meta property="og:type" content="website" />' . "\n";
        printf('<meta property="og:title" content="%s" />' . "\n", esc_attr($title));

        if ($description) {
            printf('<meta property="og:description" content="%s" />' . "\n", esc_attr($description));
        }

        printf('<meta property="og:url" content="%s" />' . "\n", esc_url($canonical));
        printf('<meta property="og:site_name" content="%s" />' . "\n", esc_attr(get_bloginfo('name')));
        printf('<link rel="canonical" href="%s" />' . "\n", esc_url($canonical));

        $this->renderAdjacentLinks();
    }

    // ── Internals ────────────────────────────────────────────────────

    private function buildTitle(): string
    {
        $cpt  = get_post_type_object(NewsCore::POST_TYPE);
        $term = $this->term();

        return sprintf(
            '%s — %s',
            $term ? $term->name : '',
            $cpt->labels->name ?? __('News', 'starwishx')
        );
    }

    private function currentPage(): int
    {
        return max(1, (int) get_query_var('paged'));
    }

    private function categoryUrl(int $page = 1): string
    {
        $term = $this->term();

        if (!$term) {
            return '';
        }

        $url = NewsCore::instance()->categoryUrl($term->slug);

        if ($page > 1) {
            global $wp_rewrite;
            $base = $wp_rewrite->pagination_base ?: 'page';
            $url  = user_trailingslashit(trailingslashit($url) . $base . '/' . $page);
        }

        return $url;
    }
}
