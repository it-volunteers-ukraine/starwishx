<?php

/**
 * Shared — the site breadcrumb trail
 *
 * File: inc/shared/Breadcrumbs/Trail.php
 */

declare(strict_types=1);

namespace Shared\Breadcrumbs;

use WP_Post;

/**
 * Builds the breadcrumb trail for the current request: data only, no markup.
 * template-parts/breadcrumbs.php renders it (visible trail + BreadcrumbList
 * JSON-LD) through sw_breadcrumbs().
 *
 * Generic contexts are handled here:
 *   - search results     → Home › Search
 *   - post-type archive  → Home › Archive label
 *   - CPT single         → Home › Archive label › Title
 *   - page               → Home › Ancestors… › Title
 *   - anything else      → Home
 * Routes that only a module knows about (e.g. /news/{category}/,
 * /opportunities/{category}/) add their crumbs through the FILTER, so this
 * class never learns their query vars or taxonomies.
 *
 * Built once per request (0–2 queries: front page, page ancestors; the post
 * type object and archive link are in memory).
 */
final class Trail
{
    /**
     * Filters the trail before it is rendered.
     *
     * @param list<array{label: string, url: ?string, home?: true}> $items
     */
    public const FILTER = 'starwishx/breadcrumbs/items';

    /** @var list<array{label: string, url: ?string, home?: true}>|null */
    private static ?array $items = null;

    /**
     * The trail for the current request, home first; the current page (if
     * any) is the last item and has no URL.
     *
     * @return list<array{label: string, url: ?string, home?: true}>
     */
    public static function items(): array
    {
        if (self::$items === null) {
            $items       = self::build();
            self::$items = $items === [] ? [] : array_values((array) apply_filters(self::FILTER, $items));
        }

        return self::$items;
    }

    /**
     * @return list<array{label: string, url: ?string, home?: true}>
     */
    private static function build(): array
    {
        $home_id  = (int) get_option('page_on_front');
        $home_url = $home_id ? get_permalink($home_id) : home_url('/');

        if (! $home_url) {
            return [];
        }

        $items = [[
            'label' => $home_id ? get_the_title($home_id) : __('Home', 'starwishx'),
            'url'   => $home_url,
            'home'  => true,
        ]];

        if (is_search()) {
            $items[] = ['label' => __('Search', 'starwishx'), 'url' => null];

            return $items;
        }

        if (is_post_type_archive()) {
            $post_type = get_query_var('post_type');
            $post_type = is_array($post_type) ? reset($post_type) : $post_type;
            $object    = get_post_type_object((string) $post_type);

            if ($object) {
                $items[] = ['label' => $object->labels->name, 'url' => null];
            }

            return $items;
        }

        $post = is_singular() ? get_queried_object() : null;
        if (! $post instanceof WP_Post) {
            return $items;
        }

        // CPT singles sit under their archive: Home › News › Title.
        if (! in_array($post->post_type, ['page', 'post'], true)) {
            $object      = get_post_type_object($post->post_type);
            $archive_url = get_post_type_archive_link($post->post_type);

            if ($object && $archive_url) {
                $items[] = ['label' => $object->labels->name, 'url' => $archive_url];
            }
        }

        // Page hierarchy, root first; the front page is already the home crumb.
        if ($post->post_type === 'page' && $post->post_parent) {
            foreach (array_reverse(get_post_ancestors($post)) as $ancestor_id) {
                if ((int) $ancestor_id === $home_id) {
                    continue;
                }
                $items[] = ['label' => get_the_title($ancestor_id), 'url' => get_permalink($ancestor_id)];
            }
        }

        $items[] = ['label' => get_the_title($post), 'url' => null];

        return $items;
    }

    /**
     * Turns the trail's current (last) crumb into a link and appends a new
     * current crumb. For modules whose routes refine an archive, e.g.
     * Home › News › {category}.
     *
     * @param  list<array{label: string, url: ?string, home?: true}> $items
     * @return list<array{label: string, url: ?string, home?: true}>
     */
    public static function appendToArchive(array $items, string $archive_url, string $label): array
    {
        $last = array_key_last($items);
        if ($last !== null && $last > 0 && empty($items[$last]['url'])) {
            $items[$last]['url'] = $archive_url;
        }
        $items[] = ['label' => $label, 'url' => null];

        return $items;
    }
}
