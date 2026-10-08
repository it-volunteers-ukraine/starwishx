<?php

/**
 * Blocks module — parsed blocks of a post, once per request
 *
 * Several head-time services read a post's block tree before the content is
 * rendered (FAQPage schema, the hero LCP preload). parse_blocks() is cheap but
 * not free on long pages; this memoises it per post (and content, so a
 * preview or a programmatic override still parses what it was given).
 *
 * File: inc/blocks/Support/PostBlocks.php
 */

declare(strict_types=1);

namespace Blocks\Support;

final class PostBlocks
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private static array $cache = [];

    /**
     * @return array<int, array<string, mixed>> Parsed blocks (see parse_blocks()).
     */
    public static function parsed(\WP_Post $post): array
    {
        $content = (string) $post->post_content;
        $key     = $post->ID . ':' . md5($content);

        if (! isset(self::$cache[$key])) {
            self::$cache[$key] = parse_blocks($content);
        }

        return self::$cache[$key];
    }
}
