<?php
// file: inc/listing/Filters/FavoritesFilter.php
declare(strict_types=1);

namespace Listing\Filters;

use Favorites\Data\FavoritesRepository;

/**
 * "Favorites" - a boolean filter, rendered for logged-in users only.
 *
 * Query: applyQuery() only tags the WP_Query args with the user id; the
 * restriction itself is where(), which ListingCore hooks on posts_where when
 * it registers this filter. No per-query state (unlike LocationsFilterSimple's
 * add/remove-hook pattern), so it holds for every query built in a request -
 * the results query and each facet context's filtered-ID subquery
 * (TermCountingService). The user's favorites stay in MySQL as a subquery
 * (FavoritesRepository::postIdsSql()): no id list in PHP, however long the
 * user's list is.
 *
 * Logged-in users' REST searches send the favorites store's nonce, so the
 * server knows the user (fetchResults() in inc/listing/Assets/grid/actions.ts;
 * an expired nonce with this filter on ends in a "reload" alert rather than an
 * unfiltered list). ListingQuery drops the flag for guests, so ?favorites=1
 * alone does nothing.
 *
 * Count: ListingService::getFacets() puts it in facets.favorites.
 */
final class FavoritesFilter extends AbstractFilter
{
    /** WP_Query var carrying the user whose favorites restrict the query. */
    public const string QUERY_VAR = 'listing_favorites_user';

    public function __construct(private readonly FavoritesRepository $favorites) {}

    #[\Override]
    public function getId(): string
    {
        return 'favorites';
    }

    #[\Override]
    public function getLabel(): string
    {
        return __('Favorites', 'starwishx');
    }

    #[\Override]
    public function applyQuery(array $args, mixed $value): array
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
    public function where(string $where, \WP_Query $query): string
    {
        $userId = (int) $query->get(self::QUERY_VAR);
        if ($userId <= 0) {
            return $where;
        }

        global $wpdb;

        return "{$where} AND {$wpdb->posts}.ID IN ({$this->favorites->postIdsSql($userId)})";
    }

    /** The count comes with the other facets (ListingService::getFacets()). */
    #[\Override]
    public function getFacetData(array $current_query_results): array
    {
        return [];
    }

    /**
     * One checkbox, so no <details> disclosure (nothing to disclose, and a
     * summary would be one more tab stop) and no role=group: the label names
     * the control. Guests get no markup at all.
     */
    #[\Override]
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
            <?php echo wp_interactivity_data_wp_context(['filterId' => $id]); ?>
            data-wp-class--has-selection="state.filterHasSelection">
            <?php echo $this->renderContent(); ?>
        </div>
<?php
        return $this->endBuffer();
    }

    #[\Override]
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
