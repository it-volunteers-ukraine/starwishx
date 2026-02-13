<?php
// file: inc/listing/Filters/CityFilter.php
declare(strict_types=1);

namespace Listing\Filters;

class CityFilter extends AbstractFilter
{
    private const META_KEY = 'city';

    public function getId(): string
    {
        return 'city';
    }

    public function getLabel(): string
    {
        return __('City', 'starwishx');
    }

    /**
     * Modifies the WP_Query to filter by city.
     */
    public function applyQuery(array $args, $value): array
    {
        if (empty($value)) return $args;

        $args['meta_query'][] = [
            'key'     => self::META_KEY,
            'value'   => sanitize_text_field($value),
            'compare' => '=',
        ];

        return $args;
    }

    /**
     * For high-cardinality fields, we don't load all options on SSR.
     * We load the TOP 10 most used cities to pre-fill the filter.
     */
    public function getFacetData(array $current_query_results): array
    {
        global $wpdb;

        // Custom SQL to get top 10 most common cities used in 'opportunity' posts
        $sql = "
            SELECT pm.meta_value as label, COUNT(pm.post_id) as count
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = %s
            AND p.post_type = 'opportunity'
            AND p.post_status = 'publish'
            GROUP BY pm.meta_value
            ORDER BY count DESC
            LIMIT 10
        ";

        $results = $wpdb->get_results($wpdb->prepare($sql, self::META_KEY));

        return array_map(fn($row) => [
            'id'    => $row->label, // Meta uses name as ID
            'label' => $row->label,
            'count' => (int) $row->count,
        ], $results);
    }

    /**
     * Renders the filter with a nested text input for searching more cities.
     */
    protected function renderContent(): string
    {
        $this->startBuffer();
?>
        <div class="filter-search-box">
            <input
                type="search"
                class="filter-search-input"
                placeholder="<?php esc_attr_e('Find city...', 'starwishx'); ?>"
                data-filter-id="<?php echo esc_attr($this->getId()); ?>"
                data-wp-on--input="actions.listing.filters.searchSubFilter">
            <span
                class="spinner is-active"
                data-wp-bind--hidden="!state.listing.ui.isLoadingCity"></span>
        </div>

        <div class="filter-list filter-list--scrollable">
            <template
                data-wp-each--item="state.facets.<?php echo esc_attr($this->getId()); ?>"
                data-wp-key="context.item.id">
                <label class="listing-radio">
                    <input
                        type="radio"
                        name="listing_city"
                        data-wp-on--change="actions.listing.filters.toggle"
                        data-wp-bind--value="context.item.id"
                        data-wp-bind--checked="actions.listing.filters.isChecked"
                        data-field="city">
                    <span class="label-text" data-wp-text="context.item.label"></span>
                    <!-- Hiding count for dynamically fetched items as standard WP_Query doesn't count them instantly -->
                    <span class="count" data-wp-text="context.item.count" data-wp-bind--hidden="!context.item.count"></span>
                </label>
            </template>
        </div>

        <!-- "Clear" button visible only when a city is selected -->
        <button
            type="button"
            class="filter-clear-btn"
            data-wp-bind--hidden="!state.query.city"
            data-wp-on--click="actions.listing.filters.clearCity">
            <?php esc_html_e('Clear selection', 'starwishx'); ?>
        </button>
<?php
        return $this->endBuffer();
    }
}
