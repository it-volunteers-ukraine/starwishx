<?php
// file: inc/listing/Services/TermCountingService.php
declare(strict_types=1);

namespace Listing\Services;

use Listing\Core\QueryBuilder;
use Listing\Enums\Taxonomy;
use WP_Query;

/**
 * Handles dynamic term counting based on current search context.
 * Used for facet filtering to show only terms with matching posts.
 */
class TermCountingService
{
    private QueryBuilder $queryBuilder;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Get term counts for a taxonomy based on current search context.
     * Uses the "Exclude Self" rule - doesn't count against the target taxonomy.
     *
     * @param Taxonomy $taxonomy The taxonomy to count
     * @param array $contextFilters Current filters (location, country, search)
     * @return array [term_id => count]
     */
    public function getCountsForContext(Taxonomy $taxonomy, array $contextFilters): array
    {
        // Build base query for context filters
        $args = $this->queryBuilder->build($contextFilters, -1);
        $args['fields'] = 'ids';
        $args['no_found_rows'] = true;

        $query = new WP_Query($args);

        if (empty($query->request)) {
            return [];
        }

        global $wpdb;
        $taxonomyValue = $taxonomy->value;

        $sql = "
            SELECT tt.term_id, COUNT(*) as count
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tt.taxonomy = %s
            AND tr.object_id IN ({$query->request})
            GROUP BY tt.term_id
        ";

        $results = $wpdb->get_results($wpdb->prepare($sql, $taxonomyValue));

        $counts = [];
        foreach ($results as $row) {
            $counts[(int)$row->term_id] = (int)$row->count;
        }

        return $counts;
    }

    /**
     * Build a hierarchical tree with dynamic counts.
     * Sums child counts into parent counts.
     * Filters out branches with zero total count.
     *
     * @param Taxonomy $taxonomy The taxonomy to build tree for
     * @param array $contextFilters Current filters for counting
     * @return array Hierarchical tree structure
     */
    public function buildFacetedTree(Taxonomy $taxonomy, array $contextFilters): array
    {
        // 1. Get all terms
        $terms = get_terms([
            'taxonomy'   => $taxonomy->value,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        // 2. Get dynamic counts based on context
        $counts = $this->getCountsForContext($taxonomy, $contextFilters);

        // 3. Build tree with dynamic counts
        return $this->buildTreeWithCounts($terms, $counts);
    }

    /**
     * Recursive tree builder with dynamic counts and zero-branch filtering.
     */
    private function buildTreeWithCounts(array $terms, array $counts, int $parentId = 0): array
    {
        $branch = [];

        foreach ($terms as $term) {
            if ($term->parent != $parentId) {
                continue;
            }

            // Get children first (post-order traversal for counting)
            $children = $this->buildTreeWithCounts($terms, $counts, $term->term_id);

            // Calculate total count = term count + children counts
            $termCount = $counts[$term->term_id] ?? 0;
            $childrenCount = array_reduce($children, fn($sum, $child) => $sum + $child['count'], 0);
            $totalCount = $termCount + $childrenCount;

            // Only include if it has count or has children with count
            if ($totalCount > 0 || !empty($children)) {
                $branch[] = [
                    'id'          => $term->term_id,
                    'label'       => $term->name,
                    'count'       => $totalCount,
                    'children'    => $children,
                    'hasChildren' => !empty($children),
                ];
            }
        }

        return $branch;
    }
}
