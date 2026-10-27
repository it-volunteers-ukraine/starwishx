<?php
// file: inc/listing/Core/DerivedState.php
declare(strict_types=1);

namespace Listing\Core;

/**
 * Server-side twins of the listing store's getters, so archive-opportunity.php
 * can go through wp_interactivity_process_directives(): the first paint, and
 * crawlers, get the results, counts, chips and hidden states the client would
 * compute. The closures never reach the client - core only tells it which
 * paths were derived, and the store's own getters take over on hydration.
 *
 * Each closure mirrors the JS getter of the same name in
 * inc/listing/Assets/grid/getters.js or inc/listing/Assets/filters/getters.js.
 * Change them together: where they disagree, the page visibly changes when the
 * client hydrates.
 */
final class DerivedState
{
    /** Longest chip label before it is cut with "…" (activeFilterChips). */
    private const CHIP_LABEL_MAX = 33;

    private const CATEGORY_FACET = 'category-oportunities';

    /**
     * For wp_interactivity_state('listing', …), next to the hydrated data.
     *
     * @return array<string, \Closure>
     */
    public static function closures(): array
    {
        return [
            // grid/getters.js
            'hasResults'                  => static fn(): bool => !empty(self::state()['results']),
            'hasMore'                     => static fn(): bool => (int) (self::state()['query']['page'] ?? 1) < (int) (self::state()['totalPages'] ?? 0),
            'resultsFoundLabel'           => static fn(): int => (int) (self::state()['totalFound'] ?? 0),
            'hasActiveFilters'            => static fn(): bool => self::activeFiltersCount() > 0,
            'activeFiltersCountLabel'     => static fn(): string => self::countLabel(self::activeFiltersCount()),
            'isFilterChecked'             => static fn(): bool => self::isFilterChecked(),
            'categoryClass'               => static fn(): string => self::categoryClass(),
            'isFavorited'                 => static fn(): bool => !empty(wp_interactivity_get_context()['item']['isFavorite']),
            'selectedCategoryName'        => static fn(): string => (string) (self::selectedParent()['label'] ?? ''),
            'hasSelectedCategory'         => static fn(): bool => (string) (self::selectedParent()['label'] ?? '') !== '',
            'selectedCategoryImageUrl'    => static fn(): ?string => ((string) (self::selectedParent()['imageUrl'] ?? '')) ?: null,
            'selectedCategoryDescription' => static fn(): string => (string) (self::selectedParent()['description'] ?? ''),
            'activeFilterChips'           => static fn(): array => self::activeFilterChips(),
            'chipRemoveLabel'             => static fn(): string => self::chipRemoveLabel(),
            // filters/getters.js
            'filterCountLabel'            => static fn(): string => self::filterCountLabel(),
            'filterHasSelection'          => static fn(): bool => self::filterHasSelection(),
            'isPartiallySelected'         => static fn(): bool => self::isPartiallySelected(),
            'isParentExpanded'            => static fn(): bool => self::isParentExpanded(),
            'parentCount'                 => static fn(): int => self::parentCount(),
        ];
    }

    /** The listing namespace's state, as StateAggregator hydrated it. */
    private static function state(): array
    {
        return wp_interactivity_state('listing');
    }

    /** JavaScript truthiness, which PHP's differs from for "0". */
    private static function truthy(mixed $value): bool
    {
        return is_string($value) ? $value !== '' : (bool) $value;
    }

    /** "(n)", or "" for none. */
    private static function countLabel(int $count): string
    {
        return $count > 0 ? "({$count})" : '';
    }

    /**
     * Ticked categories counted as the UI shows them: a ticked parent once
     * (it stands for its children), otherwise each ticked child.
     *
     * @param int[] $selected
     */
    private static function categoryCount(array $selected): int
    {
        $count = 0;

        foreach (self::state()['facets'][self::CATEGORY_FACET] ?? [] as $node) {
            if (in_array($node['id'], $selected, true)) {
                $count++;
                continue;
            }
            foreach ($node['children'] ?? [] as $child) {
                if (in_array($child['id'], $selected, true)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /** grid/getters.js activeFiltersCount: every filter but s and page. */
    private static function activeFiltersCount(): int
    {
        $count = 0;

        foreach (self::state()['query'] ?? [] as $key => $value) {
            if ($key === 's' || $key === 'page') {
                continue;
            }
            if ($key === 'category' && is_array($value) && $value) {
                $count += self::categoryCount($value);
            } elseif (is_array($value)) {
                $count += count($value);
            } elseif (self::truthy($value)) {
                $count++;
            }
        }

        return $count;
    }

    private static function isFilterChecked(): bool
    {
        $ctx   = wp_interactivity_get_context();
        $id    = $ctx['item']['id'] ?? null;
        $field = $ctx['filterField'] ?? null;
        if ($id === null || !$field) {
            return false;
        }

        $value = self::state()['query'][$field] ?? null;

        // Loose, as the JS compares with ==.
        return is_array($value) ? in_array($id, $value) : $value == $id;
    }

    private static function categoryClass(): string
    {
        $cat = wp_interactivity_get_context()['cat'] ?? null;

        return $cat ? 'category-tag ' . ($cat['slug'] ?? '') : 'category-tag';
    }

    /** The selected parent category node when exactly one is selected. */
    private static function selectedParent(): ?array
    {
        $selected = self::state()['query']['category'] ?? [];
        if (!is_array($selected) || !$selected) {
            return null;
        }

        $parents = array_values(array_filter(
            self::state()['facets'][self::CATEGORY_FACET] ?? [],
            static fn(array $node): bool => in_array($node['id'], $selected, true)
        ));

        return count($parents) === 1 ? $parents[0] : null;
    }

    /**
     * A ticked parent is one chip (its children are in it), otherwise one chip
     * per ticked child, coloured by its parent's slug. Country and beneficiary
     * labels come from their facets; the client can also use labels cached
     * from earlier responses, which a page load doesn't have yet.
     */
    private static function activeFilterChips(): array
    {
        $state = self::state();
        $query = $state['query'] ?? [];
        $chips = [];

        $selected = $query['category'] ?? [];
        if ($selected) {
            foreach ($state['facets'][self::CATEGORY_FACET] ?? [] as $node) {
                if (in_array($node['id'], $selected, true)) {
                    $chips[] = self::categoryChip($node, $node['slug'] ?? '', false);
                    continue;
                }
                foreach ($node['children'] ?? [] as $child) {
                    if (in_array($child['id'], $selected, true)) {
                        $chips[] = self::categoryChip($child, $node['slug'] ?? '', true);
                    }
                }
            }
        }

        foreach (['country' => 'country', 'beneficiaries' => 'category-beneficiaries'] as $field => $facetKey) {
            foreach ($query[$field] ?? [] as $id) {
                foreach ($state['facets'][$facetKey] ?? [] as $option) {
                    if ($option['id'] == $id) {
                        $chips[] = [
                            'key'   => "{$field}-{$id}",
                            'field' => $field,
                            'value' => $id,
                            'label' => self::chipLabel((string) $option['label']),
                        ];
                        break;
                    }
                }
            }
        }

        if (self::truthy($query['location'] ?? '')) {
            $chips[] = [
                'key'   => 'location',
                'field' => 'location',
                'value' => $query['location'],
                'label' => self::chipLabel((string) $query['location']),
            ];
        }

        return $chips;
    }

    private static function categoryChip(array $term, string $slug, bool $isChild): array
    {
        return [
            'key'     => "category-{$term['id']}",
            'field'   => 'category',
            'value'   => $term['id'],
            'label'   => self::chipLabel((string) $term['label']),
            'slug'    => $slug,
            'isChild' => $isChild,
        ];
    }

    /** "Remove filter: {chip label}" - each chip's button says which filter it removes. */
    private static function chipRemoveLabel(): string
    {
        $removeFilter = (string) (self::state()['i18n']['removeFilter'] ?? '');
        $label        = (string) (wp_interactivity_get_context()['item']['label'] ?? '');

        return $label !== '' ? "{$removeFilter}: {$label}" : $removeFilter;
    }

    /** Cut like the JS (which counts UTF-16 units - the same for Cyrillic). */
    private static function chipLabel(string $label): string
    {
        return mb_strlen($label) > self::CHIP_LABEL_MAX
            ? mb_substr($label, 0, self::CHIP_LABEL_MAX) . '…'
            : $label;
    }

    private static function filterCountLabel(): string
    {
        $filterId = wp_interactivity_get_context()['filterId'] ?? null;
        if (!$filterId) {
            return '';
        }

        $value = self::state()['query'][$filterId] ?? null;
        if (!self::truthy($value)) {
            return '';
        }
        if (is_string($value)) {
            return '(1)';
        }
        if ($filterId === 'category') {
            return self::countLabel(self::categoryCount((array) $value));
        }

        return '(' . count((array) $value) . ')';
    }

    private static function filterHasSelection(): bool
    {
        $filterId = wp_interactivity_get_context()['filterId'] ?? null;
        if (!$filterId) {
            return false;
        }

        $value = self::state()['query'][$filterId] ?? null;

        return is_array($value) ? count($value) > 0 : self::truthy($value);
    }

    private static function isPartiallySelected(): bool
    {
        $children = wp_interactivity_get_context()['item']['children'] ?? [];
        if (!$children) {
            return false;
        }

        $selected = self::state()['query']['category'] ?? [];
        $ticked   = count(array_filter(
            $children,
            static fn(array $child): bool => in_array($child['id'], $selected, true)
        ));

        return $ticked > 0 && $ticked < count($children);
    }

    private static function isParentExpanded(): bool
    {
        $ctx  = wp_interactivity_get_context();
        $item = $ctx['item'] ?? null;
        if (!$item) {
            return false;
        }

        $selected = (array) (self::state()['query'][$ctx['filterField'] ?? 'category'] ?? []);
        if (in_array($item['id'], $selected, true)) {
            return true;
        }
        foreach ($item['children'] ?? [] as $child) {
            if (in_array($child['id'], $selected, true)) {
                return true;
            }
        }

        return false;
    }

    private static function parentCount(): int
    {
        $item = wp_interactivity_get_context()['item'] ?? null;
        if (!$item) {
            return 0;
        }

        $count    = (int) ($item['count'] ?? 0);
        $children = $item['children'] ?? [];
        $selected = self::state()['query']['category'] ?? [];
        if (!$children || in_array($item['id'], $selected, true)) {
            return $count;
        }

        foreach ($children as $child) {
            if (in_array($child['id'], $selected, true)) {
                return !empty($item['selectedCount']) ? (int) $item['selectedCount'] : $count;
            }
        }

        return $count;
    }
}
