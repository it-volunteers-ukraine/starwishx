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
    /**
     * Query var that makes a WP_Query build its SQL without running it
     * (skipSqlOnly(), hooked on posts_pre_query by ListingCore).
     */
    public const SQL_ONLY = 'listing_sql_only';

    private QueryBuilder $queryBuilder;

    /** @var array<string, string> filteredIdsSql() per context, for this request. */
    private array $contextSql = [];

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * posts_pre_query: a query built only for its SQL never reaches the
     * database. WP_Query has assembled $request by the time this runs.
     */
    public static function skipSqlOnly(mixed $posts, WP_Query $query): mixed
    {
        return $query->get(self::SQL_ONLY) ? [] : $posts;
    }

    /**
     * SQL selecting the ids of the posts that match $contextFilters, to embed
     * as a subquery (`... IN (<sql>)`). WP_Query builds it, so every filter's
     * applyQuery() and the location filter's posts_clauses hook apply, but it
     * is never executed on its own - MySQL runs it once, inside the facet
     * query. Built once per distinct context per request: with no filters
     * active, all three facets share one.
     */
    public function filteredIdsSql(array $contextFilters): string
    {
        $key = md5(serialize($contextFilters));

        if (!isset($this->contextSql[$key])) {
            $args                  = $this->queryBuilder->build($contextFilters, -1);
            $args['fields']        = 'ids';
            $args['no_found_rows'] = true;
            $args[self::SQL_ONLY]  = true;

            $this->contextSql[$key] = (string) (new WP_Query($args))->request;
        }

        return $this->contextSql[$key];
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
        $ids = $this->filteredIdsSql($contextFilters);

        if ($ids === '') {
            return [];
        }

        global $wpdb;
        $taxonomyValue = $taxonomy->value;

        $sql = "
            SELECT tt.term_id, COUNT(*) as count
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tt.taxonomy = %s
            AND tr.object_id IN ({$ids})
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
     * @param int[] $selectedIds Selected term ids, for each parent's selectedCount
     * @return array Hierarchical tree structure
     */
    public function buildFacetedTree(Taxonomy $taxonomy, array $contextFilters, array $selectedIds = []): array
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

        // 4. Swap the internal post id sets for the one number the client
        //    needs: a parent's selectedCount is the distinct posts across its
        //    selected children, which it shows while only some of them are
        //    ticked (state.parentCount). The sets themselves stay here, so
        //    the payload doesn't grow with the catalog.
        $selected = array_flip(array_map('intval', $selectedIds));
        foreach ($tree as &$node) {
            $union = [];
            foreach ($node['children'] as &$child) {
                if (isset($selected[$child['id']])) {
                    foreach ($child['_postIds'] ?? [] as $postId) {
                        $union[$postId] = true;
                    }
                }
                unset($child['_postIds']);
            }
            unset($child);
            unset($node['_postIds']);
            $node['selectedCount'] = count($union);
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
        $ids = $this->filteredIdsSql($contextFilters);

        if ($ids === '') {
            return [];
        }

        global $wpdb;

        $sql = "
            SELECT tt.term_id, tr.object_id
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tt.taxonomy = %s
            AND tr.object_id IN ({$ids})
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
