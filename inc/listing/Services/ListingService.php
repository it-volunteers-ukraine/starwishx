<?php
// file: inc/listing/Services/ListingService.php
declare(strict_types=1);

namespace Listing\Services;

use Favorites\Services\FavoritesService;
use Listing\Core\QueryBuilder;
use Listing\Enums\Taxonomy;
use WP_Query;

class ListingService
{
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
     */
    public function search(array $filters): array
    {
        $args  = $this->queryBuilder->build($filters);
        $query = new WP_Query($args);

        if (empty($query->posts)) {
            return [
                'items'       => [],
                'total'       => 0,
                'total_pages' => 0,
                'page'        => (int) $args['paged'],
            ];
        }

        $postIds   = wp_list_pluck($query->posts, 'ID');
        $preloaded = $this->preloadPostData($postIds);

        $items = array_map(fn($post) => $this->formatOpportunityCard($post, $preloaded), $query->posts);

        return [
            'items'       => $items,
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'page'        => (int) $args['paged'],
        ];
    }

    /**
     * Batch-loads all per-post data needed by formatOpportunityCard() in as few
     * queries as possible.
     *
     * Term loading strategy — why update_object_term_cache() instead of wp_get_object_terms():
     *
     * wp_get_object_terms() with multiple object IDs returns each unique term only
     * once, even when it is associated with several posts. The object_id property
     * on the returned WP_Term reflects only one of the matching posts, making
     * reliable post-keyed indexing impossible.
     *
     * update_object_term_cache() issues one query per taxonomy to prime WordPress's
     * internal per-request object cache for every post in the set. Subsequent
     * get_the_terms() calls in formatOpportunityCard() then hit the cache at zero
     * DB cost, giving correct per-post results without any custom indexing logic.
     * With a persistent cache (Redis / Memcached), the taxonomy queries are also
     * skipped on warm requests.
     *
     * The full category term map (needed for parent-climbing) is stored in a WP
     * transient so it survives across requests without a persistent cache driver.
     * It is invalidated on the 'saved_term' hook — see ListingCore::bootstrap().
     *
     * Queries per request for a page of N posts:
     *   - 1 × location view      (all posts in one IN clause, always)
     *   - 3 × taxonomy cache     (update_object_term_cache, one per taxonomy, always)
     *   - 1 × get_terms          (category map, only on transient cache miss)
     *   - N × isFavorite         (logged-in users only; batch optimisation possible
     *                              via FavoritesService::getFavoriteIds())
     *
     * @param int[] $postIds
     */
    private function preloadPostData(array $postIds): array
    {
        global $wpdb;

        // --- Locations (custom view) ----------------------------------------
        $placeholders = implode(',', array_fill(0, count($postIds), '%d'));

        $locationRows = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->prepare(
                "SELECT post_id, code, name_category_oblast AS name, level, category
             FROM {$wpdb->prefix}v_opportunity_search
             WHERE post_id IN ($placeholders)",
                ...$postIds
            ),
            ARRAY_A
        );

        $locationsByPost = [];
        foreach ($locationRows as $row) {
            $pid = (int) $row['post_id'];
            unset($row['post_id']);
            $locationsByPost[$pid][] = $row;
        }

        // --- Taxonomy terms — prime the WP object cache in bulk -------------
        // One query per taxonomy (3 total). All subsequent get_the_terms() calls
        // in formatOpportunityCard() resolve from cache, not the database.
        update_object_term_cache($postIds, ['country', 'category-oportunities', 'category-seekers']);

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
        $userId      = get_current_user_id();
        $favoriteIds = [];

        if ($userId > 0) {
            foreach ($postIds as $postId) {
                if ($this->favoritesService->isFavorite($userId, $postId)) {
                    $favoriteIds[$postId] = true;
                }
            }
        }

        return [
            'locations'       => $locationsByPost,
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
    public function getFacets(array $filters): array
    {
        $facets = [];

        foreach (Taxonomy::cases() as $taxonomy) {
            // "Exclude Self" Rule: do not filter by a taxonomy when counting its own facets
            $contextFilters = $filters;
            $queryVar       = $taxonomy->getQueryVar();
            unset($contextFilters[$queryVar]);

            // For hierarchical taxonomies (Category), use the tree builder
            if ($taxonomy === Taxonomy::CATEGORY) {
                $facets[$taxonomy->value] = $this->termCounter->buildFacetedTree(
                    $taxonomy,
                    $contextFilters
                );
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

        return $facets;
    }

    /**
     * Search location options for autocomplete dropdown.
     * Uses the v_opportunity_search view for pre-formatted names.
     */
    public function searchFilterOptions(string $filterId, string $searchTerm): array|\WP_Error
    {
        global $wpdb;

        // Guard is kept as a safety net; the route's validate_callback should
        // already have rejected unsupported filter IDs before reaching here.
        if ($filterId !== 'location') {
            return new \WP_Error(
                'unsupported_filter',
                sprintf(__('Filter "%s" is not supported.', 'starwishx'), $filterId),
                ['status' => 400]
            );
        }

        $suppress = $wpdb->suppress_errors();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT
                     code                   AS id,
                     name_category_oblast   AS label,
                     level,
                     category
                 FROM {$wpdb->prefix}v_opportunity_search
                 WHERE name LIKE %s
                 ORDER BY level ASC, name ASC
                 LIMIT 20",
                '%' . $wpdb->esc_like($searchTerm) . '%'
            ),
            ARRAY_A
        );

        $wpdb->suppress_errors($suppress);

        if ($wpdb->last_error) {
            return new \WP_Error('db_error', __('Failed to fetch location options.', 'starwishx'));
        }

        return $results;
    }

    /**
     * Formats the CPT into a lean object for the iAPI Results Grid.
     * Relies on data pre-loaded by preloadPostData() to avoid N+1 queries.
     *
     * Term data (country, categories, seekers) is read via get_the_terms(), which
     * resolves from the object cache primed by update_object_term_cache() — zero
     * additional DB queries per post.
     *
     * @param array $preloaded Indexed data produced by preloadPostData().
     */
    private function formatOpportunityCard(\WP_Post $post, array $preloaded): array
    {
        $postId = $post->ID;

        $locations = $preloaded['locations'][$postId] ?? [];

        // Country — first term only (one per post by convention)
        $countryTerms = get_the_terms($postId, 'country');
        $country      = (! empty($countryTerms) && ! is_wp_error($countryTerms))
            ? $countryTerms[0]->name
            : '';

        // Categories — resolved to unique top-level ancestors
        $rawCategories = get_the_terms($postId, 'category-oportunities');
        $categories    = $this->resolveTopLevelCategories(
            (! empty($rawCategories) && ! is_wp_error($rawCategories)) ? $rawCategories : [],
            $preloaded['categoryTermMap']
        );

        // Seekers
        $seekerTerms = get_the_terms($postId, 'category-seekers');
        $seekers     = (! empty($seekerTerms) && ! is_wp_error($seekerTerms))
            ? array_map(fn($t) => ['name' => $t->name], $seekerTerms)
            : [];

        $isFavorite = isset($preloaded['favoriteIds'][$postId]);

        return [
            'id'          => $postId,
            'title'       => get_the_title($post),
            'excerpt'     => wp_trim_words($post->post_content, 38, ' …'),
            'thumbnail'   => get_the_post_thumbnail_url($post, 'medium') ?: null,
            'company'     => get_post_meta($postId, 'opportunity_company', true),
            'locations'   => $locations,
            'country'     => $country,
            'categories'  => $categories,
            'seekers'     => $seekers,
            'date_starts' => $this->formatDateForUI(get_post_meta($postId, 'opportunity_date_starts', true)),
            'date_ends'   => $this->formatDateForUI(get_post_meta($postId, 'opportunity_date_ends', true)),
            'url'         => get_permalink($post),
            'isFavorite'  => $isFavorite,
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
