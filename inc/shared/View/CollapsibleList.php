<?php

/**
 * Reusable collapsible tag list with Interactivity API toggle.
 *
 * Renders a "Show N more / Show less" toggle powered by data-wp-context.
 * Each instance is self-contained (own data-wp-interactive="shared" wrapper).
 *
 * Consumer stores must import collapsible-store.js to register the "shared" namespace:
 *   import "../../shared/Assets/collapsible-store.js";
 *
 * File: inc/shared/View/CollapsibleList.php
 */

declare(strict_types=1);

namespace Shared\View;

class CollapsibleList
{
    private static int $counter = 0;

    /**
     * Render a tag list with iAPI-powered "Show N more / Show less".
     *
     * @param array  $items        WP_Term objects, arrays with 'name' key, or strings.
     * @param string $itemClass    CSS class per <li> (e.g. 'tag-seekers').
     * @param int    $visibleCount Items visible before collapse.
     * @param array  $options      Optional overrides:
     *                             - containerClass (string, default 'tag-list')
     *                             - addTermSlug    (bool,   default false)
     */
    public static function render(
        array  $items,
        string $itemClass,
        int    $visibleCount = 3,
        array  $options = []
    ): string {
        $containerClass = $options['containerClass'] ?? 'tag-list';
        $addTermSlug    = (bool) ($options['addTermSlug'] ?? false);

        $validItems = array_values(array_filter($items, function (mixed $item): bool {
            return self::itemName($item) !== '';
        }));

        if (empty($validItems)) {
            return '';
        }

        $total = count($validItems);

        // No collapse needed — plain list, no iAPI wrapper.
        if ($total <= $visibleCount || $visibleCount <= 0) {
            return sprintf(
                '<ul class="%s">%s</ul>',
                esc_attr($containerClass),
                self::renderItems($validItems, $itemClass, $addTermSlug)
            );
        }

        $visible   = array_slice($validItems, 0, $visibleCount);
        $hidden    = array_slice($validItems, $visibleCount);
        $remaining = count($hidden);
        $uid       = 'collapsible-' . (++self::$counter);

        $labelMore = $remaining === 1
            ? __('Show 1 more', 'starwishx')
            : sprintf(
                _n('Show %d more', 'Show %d more', $remaining, 'starwishx'),
                $remaining
            );
        $labelLess = __('Show less', 'starwishx');

        // JSON_HEX_TAG  — escapes < > (prevents script injection)
        // JSON_HEX_AMP  — escapes &   (prevents entity ambiguity)
        // JSON_HEX_APOS — escapes '   (safe inside single-quoted HTML attribute)
        $context = wp_json_encode([
            'isExpanded' => false,
            'labelMore'  => $labelMore,
            'labelLess'  => $labelLess,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);

        return sprintf(
            '<div data-wp-interactive="shared" data-wp-context=\'%1$s\'>'
                . '<ul class="%2$s">%3$s</ul>'
                . '<ul id="%4$s" class="%2$s %2$s--collapsed" hidden'
                . ' data-wp-bind--hidden="!state.isCollapsibleExpanded">'
                . '%6$s</ul>'
                . '<button type="button" class="collapsible__toggle btn-tertiary"'
                . ' data-wp-on--click="actions.toggleCollapsible"'
                . ' data-wp-bind--aria-expanded="state.isCollapsibleExpanded"'
                . ' aria-controls="%4$s">'
                . '<span data-wp-text="state.collapsibleToggleLabel">%5$s</span>'
                . '</button>'
                . '</div>',
            $context,  // 1 — raw JSON (safe via JSON_HEX_* flags, NOT esc_attr)
            esc_attr($containerClass), // 2
            self::renderItems($visible, $itemClass, $addTermSlug),  // 3
            esc_attr($uid),            // 4
            esc_html($labelMore),      // 5 — SSR fallback text
            self::renderItems($hidden, $itemClass, $addTermSlug)    // 6
        );
    }

    private static function itemName(mixed $item): string
    {
        if (is_object($item)) {
            return $item->name ?? '';
        }
        if (is_array($item)) {
            return $item['name'] ?? '';
        }
        return trim((string) $item);
    }

    private static function renderItems(array $items, string $itemClass, bool $addTermSlug): string
    {
        return implode('', array_map(function (mixed $item) use ($itemClass, $addTermSlug): string {
            $name = self::itemName($item);

            $slugClass = ($addTermSlug && is_object($item) && !empty($item->slug))
                ? ' ' . esc_attr($item->slug)
                : '';

            return sprintf(
                '<li class="%s">%s</li>',
                esc_attr($itemClass . $slugClass),
                esc_html($name)
            );
        }, $items));
    }
}
