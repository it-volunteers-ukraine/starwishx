<?php

/**
 * Blocks module — LCP preload for starwishx/hero
 *
 * The hero <img> is the page's LCP element but lives tens of kilobytes below
 * </head>, so the preload scanner reaches it late. On singular pages that
 * contain the block, this adds one <link rel="preload" as="image"> per source
 * to core's wp_preload_resources() output (printed at wp_head priority 1),
 * with the exact srcset/sizes the markup uses (HeroMedia), so the browser
 * starts the download with the HTML and reuses it for the <img>.
 * has_block() is a string search, so other pages pay nothing.
 *
 * File: inc/blocks/Support/HeroPreload.php
 */

declare(strict_types=1);

namespace Blocks\Support;

final class HeroPreload
{
    public function register(): void
    {
        add_filter('wp_preload_resources', [$this, 'add']);
    }

    /**
     * @param array<int, array<string, string>> $resources
     * @return array<int, array<string, string>>
     */
    public function add($resources): array
    {
        $resources = is_array($resources) ? $resources : [];

        if (! is_singular()) {
            return $resources;
        }

        $post = get_post();
        if (! $post instanceof \WP_Post) {
            return $resources;
        }

        $attributes = HeroMedia::firstHeroAttributes($post);
        if ($attributes === null) {
            return $resources;
        }

        return array_merge($resources, HeroMedia::preloadResources($attributes));
    }
}
