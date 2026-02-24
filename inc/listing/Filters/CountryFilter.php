<?php
// file: inc/listing/Filters/CountryFilter.php
declare(strict_types=1);

namespace Listing\Filters;

/**
 * Specialized filter for the Country taxonomy.
 */
class CountryFilter extends AbstractFilter
{
    private const TAXONOMY = 'country';

    public function getId(): string
    {
        return 'country';
    }

    public function getLabel(): string
    {
        return __('Country', 'starwishx');
    }

    /**
     * Applies the country taxonomy selection to the WP_Query.
     */
    public function applyQuery(array $args, $value): array
    {
        if (empty($value)) {
            return $args;
        }

        $args['tax_query'][] = [
            'taxonomy' => self::TAXONOMY,
            'field'    => 'term_id',
            'terms'    => (array) $value,
            'operator' => 'IN',
        ];

        return $args;
    }

    /**
     * Retrieves terms and counts from the state facets.
     */
    public function getFacetData(array $current_query_results): array
    {
        $terms = get_terms([
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => true,
        ]);

        return array_map(fn($t) => [
            'id'    => $t->term_id,
            'label' => $t->name,
            'count' => $t->count,
        ], $terms);
    }

    /**
     * Renders the checkbox list for countries.
     */
    protected function renderContent(): string
    {
        $this->startBuffer();
        $storePath = $this->getStorePath(); // state.query.country
?>
        <div class="filter-list">
            <template
                data-wp-each--item="state.facets.<?php echo self::TAXONOMY; ?>"
                data-wp-key="context.item.id"
                data-wp-context='{"filterField": "country"}'>
                <label class="filter-checkbox" data-wp-context='{"filterField": "country"}'>
                    <input
                        type="checkbox"
                        data-wp-on--change="actions.filters.toggle"
                        data-wp-bind--value="context.item.id"
                        data-wp-bind--checked="state.isFilterChecked"
                        data-field="country">
                    <span class="label-text" data-wp-text="context.item.label"></span>
                    <data class="count" data-wp-bind--value="context.item.count" data-wp-text="context.item.count" aria-hidden="true"></data>
                </label>
            </template>
        </div>
<?php
        return $this->endBuffer();
    }
}
