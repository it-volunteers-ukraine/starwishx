<?php
// file: inc/listing/Services/ListingService.php
declare(strict_types=1);

namespace Listing\Services;

use Launchpad\Services\FavoritesService;
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
     * queries as possible, then indexes the results by post ID.
     *
     * Without this, formatOpportunityCard() would fire 5–10 queries per post,
     * leading to 100–200+ queries on a standard result page.
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

        // --- Taxonomy terms (batch across all posts) ------------------------
        $countryTerms  = wp_get_object_terms($postIds, 'country');
        $categoryTerms = wp_get_object_terms($postIds, 'category-oportunities');
        $seekerTerms   = wp_get_object_terms($postIds, 'category-seekers');

        $countryByPost  = $this->indexTermsByPost($countryTerms);
        $categoryByPost = $this->indexTermsByPost($categoryTerms);
        $seekersByPost  = $this->indexTermsByPost($seekerTerms);

        // --- Full category term map for parent-climbing ---------------------
        // Fetched once here so resolveTopLevelCategories() needs no DB calls.
        $allCategoryTerms = get_terms(['taxonomy' => 'category-oportunities', 'hide_empty' => false]);
        $categoryTermMap  = [];

        if (! is_wp_error($allCategoryTerms)) {
            foreach ($allCategoryTerms as $term) {
                $categoryTermMap[$term->term_id] = $term;
            }
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
            'countries'       => $countryByPost,
            'categories'      => $categoryByPost,
            'seekers'         => $seekersByPost,
            'categoryTermMap' => $categoryTermMap,
            'favoriteIds'     => $favoriteIds,
        ];
    }

    /**
     * Groups a flat array of WP_Term objects by their object_id (post ID).
     *
     * @param  \WP_Term[]|\WP_Error $terms
     * @return array<int, \WP_Term[]>
     */
    private function indexTermsByPost($terms): array
    {
        if (is_wp_error($terms)) {
            return [];
        }

        $index = [];

        foreach ($terms as $term) {
            $index[(int) $term->object_id][] = $term;
        }

        return $index;
    }

    /**
     * Climbs the category term tree using a pre-built term map and returns
     * the unique set of top-level ancestors for a post's assigned terms.
     *
     * @param  \WP_Term[] $terms       Terms assigned to the post.
     * @param  array      $termMap     All category terms keyed by term_id.
     * @return array<int, array{name: string, slug: string}>
     */
    private function resolveTopLevelCategories(array $terms, array $termMap): array
    {
        $categories = [];
        $seenSlugs  = [];

        foreach ($terms as $term) {
            // Climb to the root ancestor
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
     * Relies on data pre-loaded by preloadPostData() to avoid per-post queries.
     *
     * @param array $preloaded Indexed arrays produced by preloadPostData().
     */
    private function formatOpportunityCard(\WP_Post $post, array $preloaded): array
    {
        $postId = $post->ID;

        $locations  = $preloaded['locations'][$postId]  ?? [];

        // Country — first term only (one per post by convention)
        $countryTerms = $preloaded['countries'][$postId] ?? [];
        $country      = ! empty($countryTerms) ? $countryTerms[0]->name : '';

        // Categories — resolved to unique top-level ancestors
        $categories = $this->resolveTopLevelCategories(
            $preloaded['categories'][$postId] ?? [],
            $preloaded['categoryTermMap']
        );

        // Seekers
        $seekerTerms = $preloaded['seekers'][$postId] ?? [];
        $seekers     = array_map(fn($t) => ['name' => $t->name], $seekerTerms);

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
