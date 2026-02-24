<?php
// file: inc/listing/Filters/LocationsFilterSimple.php
declare(strict_types=1);

namespace Listing\Filters;

use Shared\Core\Traits\BufferedRenderTrait;

/**
 * Location Filter using wp_v_opportunity_search view.
 * 
 * Architecture: Uses posts_clauses hook to inject SQL subquery,
 * avoiding large ID arrays in PHP memory.
 */
class LocationsFilterSimple extends AbstractFilter
{
    use BufferedRenderTrait;

    private const VIEW_NAME = 'wp_v_opportunity_search';

    /**
     * Stores the search term for the posts_clauses hook.
     * Set in applyQuery(), consumed in injectLocationSubquery().
     */
    private ?string $searchTerm = null;

    public function getId(): string
    {
        return 'location';
    }

    public function getLabel(): string
    {
        return __('Location', 'starwishx');
    }

    /**
     * Register the posts_clauses hook to inject location filtering.
     * The actual SQL is built in injectLocationSubquery().
     */
    public function applyQuery(array $args, $value): array
    {
        if (empty($value)) {
            return $args;
        }

        $this->searchTerm = sanitize_text_field($value);
        add_filter('posts_clauses', [$this, 'injectLocationSubquery'], 10, 2);

        return $args;
    }

    /**
     * Injects a SQL subquery into WP_Query via posts_clauses.
     * 
     * This runs entirely in MySQL — no PHP memory for large ID sets.
     * 
     * @param array $clauses SQL clauses from WP_Query
     * @param \WP_Query $query The current query
     * @return array Modified clauses
     */
    public function injectLocationSubquery(array $clauses, \WP_Query $query): array
    {
        if (empty($this->searchTerm)) {
            return $clauses;
        }
        
        // Just additional guard
        // Only inject for opportunity queries (prevent pollution of unrelated queries)
        if ($query->get('post_type') !== 'opportunity') {
            return $clauses;
        }

        global $wpdb;

        // Build subquery: search by location name in the view
        $subquery = $wpdb->prepare(
            "SELECT DISTINCT post_id FROM " . self::VIEW_NAME . " WHERE name_category_oblast LIKE %s",
            '%' . $wpdb->esc_like($this->searchTerm) . '%'
        );

        // Inject as WHERE IN (subquery)
        $clauses['where'] .= " AND {$wpdb->posts}.ID IN ({$subquery})";

        // Clean up: remove hook after single use to prevent double execution
        remove_filter('posts_clauses', [$this, 'injectLocationSubquery'], 10);
        $this->searchTerm = null;

        return $clauses;
    }

    /**
     * Get facet data for initial SSR render.
     * Returns top locations by post count.
     */
    public function getFacetData(array $current_query_results): array
    {
        global $wpdb;

        // Get top 15 locations with most opportunities
        $sql = "
            SELECT 
                code as id,
                name_category_oblast as label,
                COUNT(DISTINCT post_id) as count
            FROM " . self::VIEW_NAME . "
            GROUP BY code, name_category_oblast
            ORDER BY count DESC
            LIMIT 15
        ";

        $results = $wpdb->get_results($sql);

        return array_map(fn($row) => [
            'id'    => $row->id,
            'label' => $row->label,
            'count' => (int) $row->count,
        ], $results);
    }

    /**
     * Renders the filter UI with autocomplete input.
     */
    protected function renderContent(): string
    {
        $this->startBuffer();
?>
        <div class="filter-search-box">
            <input
                type="search"
                class="filter-search-input"
                placeholder="<?php esc_attr_e('Find location...', 'starwishx'); ?>"
                data-filter-id="<?php echo esc_attr($this->getId()); ?>"
                data-wp-bind--value="state.query.location"
                data-wp-on--input="actions.filters.updateLocationSearch">
            <span
                class="spinner is-active"
                data-wp-bind--hidden="!state.listing.ui.isLoadingLocation"></span>
        </div>

        <!-- "Clear" button visible only when a location is selected -->
        <!-- <button
            type="button"
            class="filter-clear-btn"
            data-wp-bind--hidden="!state.query.location"
            data-wp-on--click="actions.filters.clearLocation">
            < ?php esc_html_e('Clear selection', 'starwishx'); ? >
        </button> -->
<?php
        return $this->endBuffer();
    }
}
