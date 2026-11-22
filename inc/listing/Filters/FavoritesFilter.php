<?php
// file: inc/listing/Filters/FavoritesFilter.php
declare(strict_types=1);

namespace Listing\Filters;

/**
 * "Favorites" - a boolean filter, rendered for logged-in users only.
 *
 * Query: applyQuery() only tags the WP_Query args with the user id; the
 * restriction itself is where(), hooked once on posts_where by ListingCore.
 * No instance state (unlike LocationsFilterSimple's add/remove-hook pattern),
 * so it holds for every query built in a request - the results query and each
 * facet context's filtered-ID subquery (TermCountingService). The favorites
 * table stays in MySQL as a subquery: no id list in PHP, however long the
 * user's list is.
 *
 * The REST search runs as a guest unless this filter is on: the client then
 * sends the favorites store's nonce (fetchJson() in inc/listing/Assets/utils.js).
 * ListingQuery drops the flag for guests, so ?favorites=1 alone does nothing.
 *
 * Count: ListingService::getFacets() puts it in facets.favorites.
 */
class FavoritesFilter extends AbstractFilter
{
    /** WP_Query var carrying the user whose favorites restrict the query. */
    public const QUERY_VAR = 'listing_favorites_user';

    public function getId(): string
    {
        return 'favorites';
    }

    public function getLabel(): string
    {
        return __('My favorites', 'starwishx');
    }

    public function applyQuery(array $args, $value): array
    {
        $userId = get_current_user_id();
        if (!$value || $userId <= 0) {
            return $args;
        }

        $args[self::QUERY_VAR] = $userId;

        return $args;
    }

    /**
     * posts_where: keep only the tagged user's favorite posts.
     */
    public static function where(string $where, \WP_Query $query): string
    {
        $userId = (int) $query->get(self::QUERY_VAR);
        if ($userId <= 0) {
            return $where;
        }

        global $wpdb;

        return $where . $wpdb->prepare(
            " AND {$wpdb->posts}.ID IN (
                SELECT object_id FROM {$wpdb->prefix}launchpad_favorites
                WHERE user_id = %d AND object_type = 'post'
            )",
            $userId
        );
    }

    /** The count comes with the other facets (ListingService::getFacets()). */
    public function getFacetData(array $current_query_results): array
    {
        return [];
    }

    /**
     * One checkbox, so no <details> disclosure (nothing to disclose, and a
     * summary would be one more tab stop) and no role=group: the label names
     * the control. Guests get no markup at all.
     */
    public function render(): string
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $id = $this->getId();

        $this->startBuffer();
?>
        <div
            class="listing-filter-group listing-filter-group--single"
            id="filter-group-<?php echo esc_attr($id); ?>"
            data-wp-interactive="listing"
            data-wp-context='{ "filterId": "<?php echo esc_attr($id); ?>" }'
            data-wp-class--has-selection="state.filterHasSelection">
            <?php echo $this->renderContent(); ?>
        </div>
<?php
        return $this->endBuffer();
    }

    protected function renderContent(): string
    {
        $this->startBuffer();
?>
        <label class="filter-checkbox filter-checkbox--favorites">
            <input
                type="checkbox"
                name="<?php echo esc_attr($this->getId()); ?>"
                value="1"
                data-wp-bind--checked="state.query.favorites"
                data-wp-on--change="actions.filters.toggleFavorites">
                <span class="label-text"><?php esc_html_e('Favorites', 'starwishx'); ?></span>
                <svg class="icon-heart" aria-hidden="true" focusable="false">
                    <use href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/img/sprites.svg#icon-heart"></use>
                </svg>
            <data class="count" data-wp-bind--value="state.facets.favorites" data-wp-text="state.facets.favorites"></data>
        </label>
<?php
        return $this->endBuffer();
    }
}
