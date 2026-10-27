<?php
// file: inc/listing/Core/ListingQuery.php
declare(strict_types=1);

namespace Listing\Core;

use Launchpad\Data\Repositories\CountriesRepository;

/**
 * The listing's filters, normalised once for both ways in: the page load
 * (StateAggregator, raw query string) and the REST search (MainController,
 * validated params plus the raw repeated keys WordPress can't parse).
 *
 * Category values may be term ids or slugs - a slug selects the term plus its
 * children that have posts, the same selection toggleParent() makes on the
 * client. Country values may be ISO numeric ids or alpha-2 codes. Unknown
 * slugs and codes are dropped. Every list is de-duplicated and capped before
 * any lookup, so a URL repeating a key thousands of times stays cheap.
 */
final class ListingQuery
{
    /**
     * Most values one list filter keeps: more than any selection the UI can
     * make (a parent plus all its children), few enough to bound the lookups.
     */
    public const LIST_MAX = 100;

    /**
     * @param int[] $category      Term ids (category-oportunities).
     * @param int[] $country       ISO numeric country ids.
     * @param int[] $beneficiaries Term ids (category-beneficiaries).
     */
    private function __construct(
        public readonly string $s,
        public readonly array $category,
        public readonly array $country,
        public readonly array $beneficiaries,
        public readonly string $location,
        public readonly int $page,
    ) {}

    /**
     * @param array<string, mixed> $raw Query-string shaped values: repeated keys
     *                                  as lists (QueryStringParser), the page as
     *                                  'paged' (WordPress) or 'page'.
     */
    public static function fromRaw(array $raw, CountriesRepository $countries): self
    {
        return new self(
            sanitize_text_field(self::scalar($raw['s'] ?? '')),
            self::resolveCategories(self::values($raw['category'] ?? [])),
            self::resolveCountries(self::values($raw['country'] ?? []), $countries),
            self::ids(self::values($raw['beneficiaries'] ?? [])),
            sanitize_text_field(self::scalar($raw['location'] ?? '')),
            max(1, absint(self::scalar($raw['paged'] ?? $raw['page'] ?? 1))),
        );
    }

    /**
     * The shape QueryBuilder, the facet counters and the client's state.query
     * use. The key order is the order syncStateToUrl() writes params in.
     */
    public function toArray(): array
    {
        return [
            's'             => $this->s,
            'category'      => $this->category,
            'country'       => $this->country,
            'location'      => $this->location,
            'beneficiaries' => $this->beneficiaries,
            'page'          => $this->page,
        ];
    }

    public function withPage(int $page): self
    {
        return new self(
            $this->s,
            $this->category,
            $this->country,
            $this->beneficiaries,
            $this->location,
            max(1, $page)
        );
    }

    /**
     * A list filter's raw values as trimmed, unique, non-empty strings,
     * capped at LIST_MAX. A single value counts as a one-item list.
     *
     * @return string[]
     */
    private static function values(mixed $raw): array
    {
        $values = [];
        foreach ((array) $raw as $value) {
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($value !== '') {
                $values[$value] = true;
            }
        }

        return array_slice(array_keys($values), 0, self::LIST_MAX);
    }

    /**
     * A scalar filter's raw value. A repeated key (?s=a&s=b) arrives as a
     * list; the last value wins, as in $_GET and WordPress' REST params.
     */
    private static function scalar(mixed $raw): string
    {
        if (is_array($raw)) {
            $raw = end($raw);
        }

        return is_scalar($raw) ? (string) $raw : '';
    }

    /**
     * @param string[] $values
     * @return int[]
     */
    private static function ids(array $values): array
    {
        return array_values(array_unique(array_filter(array_map('absint', $values))));
    }

    /**
     * Numeric ids pass through. A slug selects its term plus the children
     * that have published posts: the facet tree (TermCountingService) leaves
     * empty terms out and toggleParent() only adds the children it shows, so
     * an empty child here would be an id removeChip() can't clear.
     *
     * @param string[] $values
     * @return int[]
     */
    private static function resolveCategories(array $values): array
    {
        $ids = [];

        foreach ($values as $value) {
            if (is_numeric($value)) {
                $ids[] = absint($value);
                continue;
            }

            $term = get_term_by('slug', sanitize_text_field($value), 'category-oportunities');
            if (!$term) {
                continue;
            }

            $ids[] = $term->term_id;

            $children = get_terms([
                'taxonomy'   => 'category-oportunities',
                'parent'     => $term->term_id,
                'fields'     => 'ids',
                'hide_empty' => true,
            ]);

            if (!is_wp_error($children)) {
                $ids = array_merge($ids, array_map('absint', $children));
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * ISO numeric ids pass through; alpha-2 codes (?country=ua, the form the
     * client writes for SEO) are looked up. ListingCore 301s browser URLs
     * that still carry numeric ids.
     *
     * @param string[] $values
     * @return int[]
     */
    private static function resolveCountries(array $values, CountriesRepository $countries): array
    {
        $ids = [];

        foreach ($values as $value) {
            if (is_numeric($value)) {
                $ids[] = absint($value);
                continue;
            }

            $row = $countries->getByCode(sanitize_text_field($value));
            if ($row) {
                $ids[] = (int) $row['id'];
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
