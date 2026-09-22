<?php

/**
 * Blocks module — the hero's responsive image, in one place
 *
 * starwishx/hero shows one photo (optionally a portrait crop for phones) as a
 * full-bleed backdrop and it is the LCP element of the page. render.php and
 * the <head> preload (HeroPreload) must agree byte for byte on the candidate
 * list, or the browser downloads the image twice — so both read it from here.
 *
 * Sources are ordered the way <picture> wants them: the wide photo first
 * (`(min-width: 768px)`), the phone photo as the <img> fallback. With a single
 * photo there is no <picture> at all, just the <img>.
 *
 * File: inc/blocks/Support/HeroMedia.php
 */

declare(strict_types=1);

namespace Blocks\Support;

final class HeroMedia
{
    public const BLOCK        = 'starwishx/hero';
    public const SIZES        = '100vw';
    public const WIDE_MEDIA   = '(min-width: 768px)';
    public const NARROW_MEDIA = '(max-width: 767.98px)';

    /** Registered size whose candidates make up the srcset (all same-ratio sizes + the original ≤ 2048px). */
    private const SIZE = 'large';

    /**
     * @return array<int, array{id: int, srcset: string, media: string|null}> Wide first; [] when there is no usable image.
     */
    public static function sources(int $image_id, int $mobile_id): array
    {
        $wide   = self::candidate($image_id);
        $mobile = self::candidate($mobile_id);

        if ($wide === null) {
            // A phone-only photo still makes a hero; it just has no art direction.
            return $mobile === null ? [] : [['id' => $mobile_id, 'srcset' => $mobile, 'media' => null]];
        }

        if ($mobile === null) {
            return [['id' => $image_id, 'srcset' => $wide, 'media' => null]];
        }

        return [
            ['id' => $image_id, 'srcset' => $wide, 'media' => self::WIDE_MEDIA],
            ['id' => $mobile_id, 'srcset' => $mobile, 'media' => null],
        ];
    }

    /**
     * Entries for core's `wp_preload_resources` filter, one per source, with
     * mutually exclusive media queries so a device preloads exactly one file.
     *
     * @param array<string, mixed> $attributes Block attributes.
     * @return array<int, array<string, string>>
     */
    public static function preloadResources(array $attributes): array
    {
        $sources   = self::sources((int) ($attributes['imageId'] ?? 0), (int) ($attributes['imageMobileId'] ?? 0));
        $resources = [];

        foreach ($sources as $source) {
            $resource = [
                'as'            => 'image',
                'imagesrcset'   => $source['srcset'],
                'imagesizes'    => self::SIZES,
                'fetchpriority' => 'high',
            ];

            if (count($sources) > 1) {
                $resource['media'] = $source['media'] ?? self::NARROW_MEDIA;
            }

            $resources[] = $resource;
        }

        return $resources;
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
     * srcset for an attachment, or null when it is not a usable image.
     * Falls back to a single candidate when WordPress has no sub-sizes for it.
     */
    private static function candidate(int $attachment_id): ?string
    {
        if ($attachment_id <= 0 || ! wp_attachment_is_image($attachment_id)) {
            return null;
        }

        $srcset = wp_get_attachment_image_srcset($attachment_id, self::SIZE);
        if (is_string($srcset) && $srcset !== '') {
            return $srcset;
        }

        $src = wp_get_attachment_image_src($attachment_id, self::SIZE);
        if (! is_array($src) || empty($src[0])) {
            return null;
        }

        return $src[0] . ' ' . (int) $src[1] . 'w';
    }
}
