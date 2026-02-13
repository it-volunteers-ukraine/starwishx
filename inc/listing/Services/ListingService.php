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
        $this->queryBuilder = $queryBuilder;
        $this->termCounter = $termCounter;
        $this->favoritesService = $favoritesService ?? new FavoritesService();
    }

    /**
     * Executes the main search and formats results for iAPI cards.
     */
    public function search(array $filters): array
    {
        $args = $this->queryBuilder->build($filters);
        $query = new WP_Query($args);

        $items = array_map([$this, 'formatOpportunityCard'], $query->posts);

        return [
            'items'       => $items,
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'page'        => (int) $args['paged'],
        ];
    }

    /**
     * Calculates facet counts dynamically using the Enum.
     */
    public function getFacets(array $filters): array
    {
        $facets = [];

        foreach (Taxonomy::cases() as $taxonomy) {
            // "Exclude Self" Rule
            $contextFilters = $filters;
            $queryVar = $taxonomy->getQueryVar();
            unset($contextFilters[$queryVar]);

            // For hierarchical taxonomy (Category), use the tree builder
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

        // Only handle 'location' filter
        if ($filterId !== 'location') {
            return [];
        }

        $suppress = $wpdb->suppress_errors();

        // Search by name in the view, return formatted results
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT 
            code as id,
            name_category_oblast as label,
            level,
            category
        FROM wp_v_opportunity_search
        WHERE name LIKE %s
        ORDER BY level ASC, name ASC
        LIMIT 20",
            '%' . $wpdb->esc_like($searchTerm) . '%'
        ), ARRAY_A);

        $wpdb->suppress_errors($suppress);

        if ($wpdb->last_error) {
            return new \WP_Error('db_error', __('Failed to fetch location options.', 'starwishx'));
        }

        return $results;
    }

    /**
     * Formats the CPT into a lean object for the iAPI Results Grid.
     */
    private function formatOpportunityCard(\WP_Post $post): array
    {
        global $wpdb;

        // Fetch locations from the view
        $locations = $wpdb->get_results($wpdb->prepare(
            "SELECT code, name_category_oblast as name, level, category 
         FROM wp_v_opportunity_search 
         WHERE post_id = %d",
            $post->ID
        ), ARRAY_A);

        // one per post (or no one)
        $country_terms = get_the_terms($post->ID, 'country');
        $country = !empty($country_terms) && !is_wp_error($country_terms)
            ? $country_terms[0]->name
            : '';

        // Fetch top-level categories (parent = 0) with name AND slug
        $terms = get_the_terms($post->ID, 'category-oportunities');
        $categories = [];

        if (! empty($terms) && ! is_wp_error($terms)) {
            foreach ($terms as $term) {
                // climb to top-level parent
                while ($term->parent !== 0) {
                    $term = get_term($term->parent, 'category-oportunities');
                    if (is_wp_error($term) || ! $term) {
                        break;
                    }
                }
                if ($term && ! is_wp_error($term)) {
                    // Store as object with name and slug
                    $categories[] = [
                        'name' => $term->name,
                        'slug' => $term->slug,
                    ];
                }
            }

            // Remove duplicates by slug
            $seenSlugs = [];
            $categories = array_values(array_filter($categories, function ($cat) use (&$seenSlugs) {
                if (in_array($cat['slug'], $seenSlugs)) {
                    return false;
                }
                $seenSlugs[] = $cat['slug'];
                return true;
            }));
        }

        // Fetch seekers (can be multiple per post)
        $seeker_terms = get_the_terms($post->ID, 'category-seekers');
        $seekers = !empty($seeker_terms) && !is_wp_error($seeker_terms)
            ? array_map(fn($t) => ['name' => $t->name], $seeker_terms)
            : [];

        $userId = get_current_user_id();
        $isFavorite = $userId > 0
            ? $this->favoritesService->isFavorite($userId, $post->ID)
            : false;

        return [
            'id'         => $post->ID,
            'title'      => get_the_title($post),
            'excerpt'    => wp_trim_words(get_post_meta($post->ID, 'opportunity_description', true), 25),
            'thumbnail'  => get_the_post_thumbnail_url($post, 'medium') ?: null,
            'company'    => get_post_meta($post->ID, 'opportunity_company', true),
            'locations'  => $locations,
            'country'    => $country,
            'categories' => $categories,
            'seekers'    => $seekers,
            'date_starts' => $this->formatDateForUI(get_post_meta($post->ID, 'opportunity_date_starts', true)),
            'date_ends'  => $this->formatDateForUI(get_post_meta($post->ID, 'opportunity_date_ends', true)),
            'url'        => get_permalink($post),
            'isFavorite' => $isFavorite,
        ];
    }

    /**
     * Helper: Convert ACF 'd/m/Y' to HTML5 'Y-m-d' for date inputs.
     */
    private function formatDateForInput($dateStr): string
    {
        if (empty($dateStr)) {
            return '';
        }
        $date = \DateTime::createFromFormat('d/m/Y', $dateStr);
        return $date ? $date->format('Y-m-d') : '';
    }

    /**
     * Helper: Convert various date strings to a UI-friendly format (d.m.y).
     * 
     * @param string|null $dateStr Raw date from DB (usually Ymd or d/m/Y)
     * @param string $format The target format, defaults to d.m.y
     * @return string
     */
    private function formatDateForUI(?string $dateStr, string $format = 'd.m.y'): string
    {
        if (empty($dateStr)) {
            return '';
        }

        // 1. Try ACF's raw DB format (Ymd - 20210903)
        $date = \DateTime::createFromFormat('Ymd', $dateStr);

        // 2. Fallback: Try d/m/Y (if ACF formatting was applied)
        if (!$date) {
            $date = \DateTime::createFromFormat('d/m/Y', $dateStr);
        }

        // 3. Fallback: Try standard Y-m-d
        if (!$date) {
            $date = \DateTime::createFromFormat('Y-m-d', $dateStr);
        }

        return $date ? $date->format($format) : $dateStr;
    }
}
