<?php
// file: inc/listing/Services/ListingService.php
declare(strict_types=1);

namespace Listing\Services;

use Favorites\Services\FavoritesService;
use Listing\Core\ListingQuery;
use Listing\Core\QueryBuilder;
use Listing\Enums\Taxonomy;
use WP_Query;

class ListingService
{
    /**
     * Longest keyword (`s`) and location query the listing accepts. The
     * /search args in MainController validate against these and the sidebar
     * inputs carry them as maxlength, so typing can't produce a request the
     * API rejects.
     */
    public const SEARCH_MAX_LENGTH   = 200;
    public const LOCATION_MAX_LENGTH = 100;

    private QueryBuilder $queryBuilder;
    private TermCountingService $termCounter;
    private FavoritesService $favoritesService;

    public function __construct(
        QueryBuilder $queryBuilder,
        TermCountingService $termCounter,
        ?FavoritesService $favoritesService = null
    ) {
        $this->queryBuilder     = $queryBuilder;
        $this->termCounter      = $termCounter;
        // TODO: wire FavoritesService explicitly in ListingCore
        $this->favoritesService = $favoritesService ?? new FavoritesService();
    }

    /**
     * Executes the main search and formats results for iAPI cards.
     * Post data is pre-loaded in bulk before formatting to avoid N+1 queries.
     *
     * @param int $perPage Page size. StateAggregator passes a multiple of
     *                     ITEMS_PER_PAGE to render several pages at once.
     */
    public function search(ListingQuery $query, int $perPage = QueryBuilder::ITEMS_PER_PAGE): array
    {
        $args  = $this->queryBuilder->build($query->toArray(), $perPage);
        $query = new WP_Query($args);

        if (empty($query->posts)) {
            return [
                'items'       => [],
                'total'       => 0,
                'total_pages' => 0,
                'page'        => (int) $args['paged'],
            ];
        }

        $preloaded = $this->preloadPostData();

        $items = array_map(fn($post) => $this->formatOpportunityCard($post, $preloaded), $query->posts);

        return [
            'items'       => $items,
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'page'        => (int) $args['paged'],
        ];
    }

    /**
     * Batch-loads what formatOpportunityCard() needs beyond the posts
     * themselves, so formatting a page costs no per-card queries.
     *
     * Terms and post meta are already cached: WP_Query primes both for the
     * posts it returns (update_post_term_cache / update_post_meta_cache), so
     * get_the_terms() and get_post_meta() in formatOpportunityCard() are
     * cache hits.
     *
     * The full category term map (needed for parent-climbing) is stored in a WP
     * transient so it survives across requests without a persistent cache driver.
     * It is invalidated on the 'saved_term' hook — see ListingCore::bootstrap().
     *
     * Queries per request:
     *   - 1 × get_terms          (category map, only on transient cache miss)
     *   - 1 × favorite ids       (logged-in page loads only - REST reads run
     *                              as a guest, see fetchJson() in Assets/utils.js)
     */
    private function preloadPostData(): array
    {
        // --- Full category term map for parent-climbing ---------------------
        // Cached via transient so the get_terms() query is only paid once per hour
        // (or until a term is saved). Invalidated in ListingCore::bootstrap() via
        // the 'saved_term' action on the 'category-oportunities' taxonomy.
        $categoryTermMap = get_transient('listing_category_term_map');

        if ($categoryTermMap === false) {
            $allCategoryTerms = get_terms(['taxonomy' => 'category-oportunities', 'hide_empty' => false]);
            $categoryTermMap  = [];

            if (! is_wp_error($allCategoryTerms)) {
                foreach ($allCategoryTerms as $term) {
                    $categoryTermMap[$term->term_id] = $term;
                }
            }

            set_transient('listing_category_term_map', $categoryTermMap, HOUR_IN_SECONDS);
        }

        // --- Favorites (current user only) ----------------------------------
        // The user's ids in one query, not one isFavorite() query per card.
        $userId      = get_current_user_id();
        $favoriteIds = $userId > 0
            ? array_fill_keys(
                $this->favoritesService->getRepository()->getFavoriteIds($userId, 'post', 9999, 0),
                true
            )
            : [];

        return [
            'categoryTermMap' => $categoryTermMap,
            'favoriteIds'     => $favoriteIds,
        ];
    }

    /**
     * Climbs the category term tree using a pre-built term map and returns
     * the unique set of top-level ancestors for a post's assigned terms.
     *
     * @param  \WP_Term[] $terms    Terms assigned to the post.
     * @param  array      $termMap  All category terms keyed by term_id.
     * @return array<int, array{name: string, slug: string}>
     */
    private function resolveTopLevelCategories(array $terms, array $termMap): array
    {
        $categories = [];
        $seenSlugs  = [];

        foreach ($terms as $term) {
            // Climb to the root ancestor using the pre-loaded map (no DB calls)
            $current = $term;

            while ($current->parent !== 0 && isset($termMap[$current->parent])) {
                $current = $termMap[$current->parent];
            }

            if (! in_array($current->slug, $seenSlugs, true)) {
                $seenSlugs[]  = $current->slug;
                $categories[] = [
                    'name' => $current->name,
                    'slug' => $current->slug,
                ];
            }
        }

        return $categories;
    }

    /**
     * Calculates facet counts dynamically using the Taxonomy Enum.
     */
    public function getFacets(ListingQuery $query): array
    {
        $filters = $query->toArray();
        $facets  = [];

        foreach (Taxonomy::cases() as $taxonomy) {
            // "Exclude Self" Rule: do not filter by a taxonomy when counting its own facets
            $contextFilters = $filters;
            $queryVar       = $taxonomy->getQueryVar();
            unset($contextFilters[$queryVar]);

            // For hierarchical taxonomies (Category), use the tree builder
            if ($taxonomy === Taxonomy::CATEGORY) {
                $facets[$taxonomy->value] = $this->termCounter->buildFacetedTree(
                    $taxonomy,
                    $contextFilters,
                    $query->category
                );
                $this->enrichCategoryNodes($facets[$taxonomy->value]);
                continue;
            }

            // For flat taxonomies, use simple counting
            $counts = $this->termCounter->getCountsForContext($taxonomy, $contextFilters);

            $terms = get_terms([
                'taxonomy'   => $taxonomy->value,
                'hide_empty' => false,
            ]);

            if (is_wp_error($terms)) {
                $facets[$taxonomy->value] = [];
                continue;
            }

            $activeTerms = array_filter($terms, fn($t) => ($counts[$t->term_id] ?? 0) > 0);

            $facets[$taxonomy->value] = array_values(array_map(fn($t) => [
                'id'    => $t->term_id,
                'label' => $t->name,
                'count' => $counts[$t->term_id] ?? 0,
            ], $activeTerms));
        }

        // Country lives in wp_opportunity_countries — outside Taxonomy::cases()
        // because the typed-table path doesn't share term_id semantics with
        // the term_relationships joins in TermCountingService. Same "Exclude
        // Self" rule applied: drop country from the context before counting.
        $contextForCountry = $filters;
        unset($contextForCountry['country']);
        $facets['country'] = $this->buildCountryFacets($contextForCountry);

        return $facets;
    }

    /**
     * Build country facets from wp_opportunity_countries joined with
     * wp_sw_countries, scoped to the post set produced by the current
     * filter context.
     *
     * The post selection is TermCountingService::filteredIdsSql() - built,
     * not run, and shared with any other facet on the same context - which
     * MySQL evaluates once as the subquery here.
     */
    private function buildCountryFacets(array $contextFilters): array
    {
        $ids = $this->termCounter->filteredIdsSql($contextFilters);

        if ($ids === '') {
            return [];
        }

        global $wpdb;
        $sw = $wpdb->prefix . 'sw_countries';
        $oc = $wpdb->prefix . 'opportunity_countries';

        // `code` is exposed alongside id so the client can serialize
        // ?country=pl in syncStateToUrl without a separate dictionary
        // round trip. Server-side 301 catches any URL that arrives
        // with the numeric form.
        $rows = $wpdb->get_results(
            "SELECT c.id, c.code, c.name AS label, COUNT(DISTINCT oc.post_id) AS count
             FROM {$sw} c
             INNER JOIN {$oc} oc ON oc.country_id = c.id
             WHERE oc.post_id IN ({$ids})
             GROUP BY c.id
             ORDER BY c.priority ASC, c.name ASC",
            ARRAY_A
        );

        return array_map(static fn(array $r): array => [
            'id'    => (int) $r['id'],
            'code'  => (string) $r['code'],
            'label' => (string) $r['label'],
            'count' => (int) $r['count'],
        ], $rows ?: []);
    }

    /**
     * Enrich top-level category nodes with display data for the archive header.
     * Term objects and their meta are already in WP's object cache from
     * buildFacetedTree()'s get_terms(), so get_term() and get_term_meta() here
     * are free.
     *
     * The image is the attachment id the ACF field `cat_opportunity_image`
     * stores in term meta. All of them are primed in one go - ACF's
     * get_field() loaded each attachment with two queries of its own.
     *
     * @param array &$nodes Top-level category tree nodes (modified in place).
     */
    private function enrichCategoryNodes(array &$nodes): void
    {
        $imageIds = [];
        foreach ($nodes as $node) {
            $imageId = (int) get_term_meta($node['id'], 'cat_opportunity_image', true);
            if ($imageId > 0) {
                $imageIds[$node['id']] = $imageId;
            }
        }
        if ($imageIds) {
            _prime_post_caches(array_values($imageIds), false, true);
        }

        foreach ($nodes as &$node) {
            $term = get_term($node['id'], 'category-oportunities');
            $node['description'] = ($term && !is_wp_error($term)) ? $term->description : '';
            $node['imageUrl']    = isset($imageIds[$node['id']])
                ? (wp_get_attachment_image_url($imageIds[$node['id']], 'medium') ?: '')
                : '';
        }
        unset($node);
    }

    /**
     * Formats the CPT into a lean object for the iAPI Results Grid.
     * Relies on data pre-loaded by preloadPostData() to avoid N+1 queries.
     *
     * Only what the card renders (ResultsGrid::renderCard()). The thumbnail,
     * company, locations, country and beneficiaries it no longer shows were
     * dropped here; a card design that brings one back should load it in
     * bulk in preloadPostData(), not per card.
     *
     * Categories are read via get_the_terms(), which resolves from the term
     * cache WP_Query primed — no extra DB queries per post.
     *
     * @param array $preloaded Indexed data produced by preloadPostData().
     */
    private function formatOpportunityCard(\WP_Post $post, array $preloaded): array
    {
        $postId = $post->ID;

        // Categories — resolved to unique top-level ancestors
        $rawCategories = get_the_terms($postId, 'category-oportunities');
        $categories    = $this->resolveTopLevelCategories(
            (! empty($rawCategories) && ! is_wp_error($rawCategories)) ? $rawCategories : [],
            $preloaded['categoryTermMap']
        );

        $isFavorite   = isset($preloaded['favoriteIds'][$postId]);
        // Rating & comment engagement data (post meta cache already primed by WP_Query)
        $ratingAvg    = (float) get_post_meta($postId, '_opportunity_rating_avg', true);
        $ratingCount  = (int) get_post_meta($postId, '_opportunity_rating_count', true);
        $commentCount = (int) $post->comment_count;

        return [
            'id'            => $postId,
            'title'         => html_entity_decode(get_the_title($post)),
            'excerpt'       => wp_trim_words($post->post_content, 38, ' …'),
            'categories'    => $categories,
            'date_starts'   => $this->formatDateForUI(get_post_meta($postId, 'opportunity_date_starts', true)),
            'date_ends'     => $this->formatDateForUI(get_post_meta($postId, 'opportunity_date_ends', true)),
            'url'           => get_permalink($post),
            'isFavorite'    => $isFavorite,
            'ratingAvg'     => $ratingCount > 0 ? $ratingAvg : 0,
            'ratingRounded' => $ratingCount > 0 ? (int) round($ratingAvg) : 0,
            'ratingCount'   => $ratingCount,
            'commentCount'  => $commentCount,
            'commentsUrl'   => get_permalink($post) . '#comments',
        ];
    }

    /**
     * Helper: Convert various date strings to a UI-friendly format (d.m.y).
     *
     * @param string|null $dateStr Raw date from DB (ACF Ymd or d/m/Y)
     * @param string      $format  Target format, defaults to d.m.y
     */
    private function formatDateForUI(?string $dateStr, string $format = 'd.m.y'): string
    {
        if (empty($dateStr)) {
            return '';
        }

        // 1. Try ACF's raw DB format (Ymd — e.g. 20210903)
        $date = \DateTime::createFromFormat('Ymd', $dateStr);

        // 2. Fallback: Try d/m/Y (if ACF formatting was already applied)
        if (! $date) {
            $date = \DateTime::createFromFormat('d/m/Y', $dateStr);
        }

        // 3. Fallback: Try standard Y-m-d
        if (! $date) {
            $date = \DateTime::createFromFormat('Y-m-d', $dateStr);
        }

        return $date ? $date->format($format) : $dateStr;
    }
}
