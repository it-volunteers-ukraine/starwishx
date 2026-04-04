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
     * Parent counts are deduplicated: a post assigned to multiple children
     * under the same parent is counted once, not per-child.
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

        // 2. Get post ID sets per term (enables deduplication for parent counts)
        $postIdsByTerm = $this->getPostIdsByTerm($taxonomy, $contextFilters);

        // 3. Build tree with deduplicated counts
        $tree = $this->buildTreeWithPostIdSets($terms, $postIdsByTerm);

        // 4. Clean internal _postIds: strip from parents, expose on children
        //    Children keep postIds so the client can compute dynamic parent
        //    counts via Set union (deduplicating multi-child assignments).
        foreach ($tree as &$node) {
            unset($node['_postIds']);
            foreach ($node['children'] as &$child) {
                $child['postIds'] = $child['_postIds'] ?? [];
                unset($child['_postIds']);
            }
            unset($child);
        }
        unset($node);

        return $tree;
    }

    /**
     * Get post IDs grouped by term for a taxonomy in the current search context.
     *
     * Returns raw (term_id → post_id[]) sets instead of pre-aggregated counts.
     * This allows parent nodes to compute unique post counts via set union,
     * avoiding double-counting posts assigned to multiple sibling children.
     *
     * @param Taxonomy $taxonomy The taxonomy to query
     * @param array $contextFilters Current filters (with "self" excluded)
     * @return array<int, int[]> Map of term_id to array of post IDs
     */
    private function getPostIdsByTerm(Taxonomy $taxonomy, array $contextFilters): array
    {
        $args = $this->queryBuilder->build($contextFilters, -1);
        $args['fields'] = 'ids';
        $args['no_found_rows'] = true;

        $query = new WP_Query($args);

        if (empty($query->request)) {
            return [];
        }

        global $wpdb;

        $sql = "
            SELECT tt.term_id, tr.object_id
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tt.taxonomy = %s
            AND tr.object_id IN ({$query->request})
        ";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $taxonomy->value));

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->term_id][] = (int) $row->object_id;
        }

        return $map;
    }

    /**
     * Recursive tree builder using post ID sets for deduplicated parent counts.
     *
     * Leaf nodes: count = number of post IDs assigned to that term.
     * Parent nodes: count = number of unique post IDs across all children
     *               (set union, not sum — avoids double-counting).
     *
     * @param array $terms All terms for the taxonomy
     * @param array<int, int[]> $postIdsByTerm Map of term_id to post ID arrays
     * @param int $parentId Current parent term ID (0 for root)
     * @return array Tree nodes with 'count' and internal '_postIds' for propagation
     */
    private function buildTreeWithPostIdSets(array $terms, array $postIdsByTerm, int $parentId = 0): array
    {
        $branch = [];

        foreach ($terms as $term) {
            if ($term->parent != $parentId) {
                continue;
            }

            // Post-order: build children first so we can merge their post IDs
            $children = $this->buildTreeWithPostIdSets($terms, $postIdsByTerm, $term->term_id);

            if (empty($children)) {
                // Leaf node: count is simply the number of posts with this term
                $postIds = $postIdsByTerm[$term->term_id] ?? [];
                $count = count($postIds);
            } else {
                // Parent node: merge all children's post ID sets and deduplicate.
                // Exclude posts directly assigned to the parent term itself
                // (same "exclude mistakenly assigned Parent" rule as before).
                $mergedPostIds = [];
                foreach ($children as $child) {
                    // array_merge is fine here; we deduplicate once via array_unique
                    $mergedPostIds = array_merge($mergedPostIds, $child['_postIds']);
                }
                $postIds = array_values(array_unique($mergedPostIds));
                $count = count($postIds);
            }

            if ($count > 0 || !empty($children)) {
                $branch[] = [
                    'id'          => $term->term_id,
                    'slug'        => $term->slug,
                    'label'       => $term->name,
                    'count'       => $count,
                    'children'    => $children,
                    'hasChildren' => !empty($children),
                    '_postIds'    => $postIds, // internal, cleaned up in buildFacetedTree()
                ];
            }
        }

        return $branch;
    }
}
