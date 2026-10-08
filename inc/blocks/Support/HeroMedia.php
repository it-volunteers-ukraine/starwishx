<?php

/**
 * Blocks module — the hero's responsive image, in one place
 *
 * starwishx/hero shows one photo as a full-bleed backdrop and it is the LCP
 * element of the page. The editor picks a fallback photo (JPEG/PNG) and up to
 * two modern-format versions of it (AVIF, WebP); the block renders them as a
 * <picture> with type-based <source>s and the fallback <img>. render.php and
 * the <head> preload (HeroPreload) must agree byte for byte on the candidate
 * lists, or the browser downloads the image twice — so both read them here.
 *
 * Preload links cannot express a fallback chain (a browser that supports
 * every listed type would fetch them all), so only the first source is
 * preloaded, with its `type`, and browsers without that format simply find
 * the <picture> later — every current browser supports AVIF and WebP.
 *
 * File: inc/blocks/Support/HeroMedia.php
 */

declare(strict_types=1);

namespace Blocks\Support;

final class HeroMedia
{
    public const BLOCK = 'starwishx/hero';
    public const SIZES = '100vw';

    /** Registered size whose candidates make up the srcset (all same-ratio sizes + the original ≤ 2048px). */
    private const SIZE = 'large';

    /** Attribute names of the format sources, in preference order. */
    private const SOURCE_ATTRIBUTES = ['sourceOneId', 'sourceTwoId'];

    /**
     * @param int[] $source_ids Modern-format versions, most preferred first.
     * @return array{fallback: array{id: int, srcset: string, type: string}|null, sources: array<int, array{id: int, srcset: string, type: string}>}
     */
    public static function sources(int $image_id, array $source_ids): array
    {
        $fallback = self::candidate($image_id);
        $sources  = [];
        $seen     = $fallback ? [$fallback['type'] => true] : [];

        foreach ($source_ids as $source_id) {
            $source = self::candidate((int) $source_id);
            // A source in the fallback's own format (or a repeated format) buys nothing.
            if ($source === null || isset($seen[$source['type']])) {
                continue;
            }
            $seen[$source['type']] = true;
            $sources[]             = $source;
        }

        if ($fallback === null && $sources) {
            // No fallback picked: promote the last source to <img> so the hero still renders.
            $fallback = array_pop($sources);
        }

        return ['fallback' => $fallback, 'sources' => $sources];
    }

    /**
     * Same as sources() but read from block attributes.
     *
     * @param array<string, mixed> $attributes
     * @return array{fallback: array{id: int, srcset: string, type: string}|null, sources: array<int, array{id: int, srcset: string, type: string}>}
     */
    public static function fromAttributes(array $attributes): array
    {
        $source_ids = [];
        foreach (self::SOURCE_ATTRIBUTES as $attribute) {
            $source_ids[] = (int) ($attributes[$attribute] ?? 0);
        }

        return self::sources((int) ($attributes['imageId'] ?? 0), $source_ids);
    }

    /**
     * Entry for core's `wp_preload_resources` filter: the first source (with its
     * type) or the fallback image; [] when there is no image.
     *
     * @param array<string, mixed> $attributes Block attributes.
     * @return array<int, array<string, string>>
     */
    public static function preloadResources(array $attributes): array
    {
        $media  = self::fromAttributes($attributes);
        $target = $media['sources'][0] ?? $media['fallback'];

        if ($target === null) {
            return [];
        }

        $resource = [
            'as'            => 'image',
            'imagesrcset'   => $target['srcset'],
            'imagesizes'    => self::SIZES,
            'fetchpriority' => 'high',
        ];

        if ($media['sources']) {
            $resource['type'] = $target['type'];
        }

        return [$resource];
    }

    /**
     * Attributes of the first starwishx/hero block in a post (any nesting), or null.
     *
     * @return array<string, mixed>|null
     */
    public static function firstHeroAttributes(\WP_Post $post): ?array
    {
        if (! has_block(self::BLOCK, $post)) {
            return null;
        }

        return self::findFirst(PostBlocks::parsed($post));
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<string, mixed>|null
     */
    private static function findFirst(array $blocks): ?array
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            if (($block['blockName'] ?? '') === self::BLOCK) {
                return (array) ($block['attrs'] ?? []);
            }
            if (! empty($block['innerBlocks'])) {
                $found = self::findFirst($block['innerBlocks']);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * srcset + mime type for an attachment, or null when it is not a usable image.
     * Falls back to a single candidate when WordPress has no sub-sizes for it.
     *
     * @return array{id: int, srcset: string, type: string}|null
     */
    private static function candidate(int $attachment_id): ?array
    {
        if ($attachment_id <= 0 || ! wp_attachment_is_image($attachment_id)) {
            return null;
        }

        $type = (string) get_post_mime_type($attachment_id);
        if (! str_starts_with($type, 'image/')) {
            return null;
        }

        $srcset = wp_get_attachment_image_srcset($attachment_id, self::SIZE);
        if (! is_string($srcset) || $srcset === '') {
            $src = wp_get_attachment_image_src($attachment_id, self::SIZE);
            if (! is_array($src) || empty($src[0])) {
                return null;
            }
            $srcset = $src[0] . ' ' . (int) $src[1] . 'w';
        }

        return ['id' => $attachment_id, 'srcset' => $srcset, 'type' => $type];
    }
}
