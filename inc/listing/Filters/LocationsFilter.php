<?php

/**
 * ? Deprecated / postponded for further realization
 * 
 */
// file: inc/listing/Filters/LocationsFilterSimple.php
declare(strict_types=1);

namespace Listing\Filters;

use Shared\Core\Traits\BufferedRenderTrait;

class LocationsFilterSimple extends AbstractFilter
{
    use BufferedRenderTrait;

    private const VIEW_NAME = 'wp_v_opportunity_search';
    private ?string $searchTerm = null;

    public function getId(): string
    {
        return 'location';
    }

    public function getLabel(): string
    {
        return __('Location', 'starwishx');
    }

    public function applyQuery(array $args, $value): array
    {
        if (empty($value)) {
            return $args;
        }

        $this->searchTerm = sanitize_text_field($value);
        add_filter('posts_clauses', [$this, 'injectLocationSubquery'], 10, 2);

        return $args;
    }

    public function injectLocationSubquery(array $clauses, \WP_Query $query): array
    {
        if (empty($this->searchTerm)) {
            return $clauses;
        }

        global $wpdb;

        // Build subquery with proper escaping
        $escapedSearch = '%' . $wpdb->esc_like($this->searchTerm) . '%';
        $subquery = $wpdb->prepare(
            "SELECT DISTINCT post_id FROM " . self::VIEW_NAME . " WHERE name LIKE %s",
            $escapedSearch
        );
        $subquery = $wpdb->remove_placeholder_escape($subquery);

        $clauses['where'] .= " AND {$wpdb->posts}.ID IN ({$subquery})";

        remove_filter('posts_clauses', [$this, 'injectLocationSubquery'], 10);
        $this->searchTerm = null;

        return $clauses;
    }

    public function getFacetData(array $current_query_results): array
    {
        // Return empty facets since we're doing immediate search
        // Or keep the top locations for suggestions
        global $wpdb;

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

    protected function renderContent(): string
    {
        $this->startBuffer();
?>
        <div class="filter-search-box">
            <input
                type="search"
                class="filter-search-input"
                placeholder="<?php esc_attr_e('Find location...', 'starwishx'); ?>"
                data-wp-bind--value="state.query.location"
                data-wp-on--input="actions.filters.updateLocationSearch">
        </div>

        <button
            type="button"
            class="filter-clear-btn"
            data-wp-bind--hidden="!state.query.location"
            data-wp-on--click="actions.filters.clearLocation">
            <?php esc_html_e('Clear', 'starwishx'); ?>
        </button>
<?php
        return $this->endBuffer();
    }
}
