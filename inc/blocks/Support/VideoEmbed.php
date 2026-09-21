<?php

/**
 * Blocks module — video URL → embed URL
 *
 * Turns whatever an editor pastes into the "Video URL" attribute (a YouTube or
 * Vimeo page URL, share URL, embed URL, or the whole <iframe> snippet the old
 * ACF oEmbed field stored) into a bare player URL, so render.php never touches
 * the network. Unknown providers return null and fall back to WP_Embed.
 *
 * Lives in an autoloaded class rather than render.php: WordPress `require`s a
 * block's render file once per instance, so a function declared there would
 * fatal on the second block of the same type on a page.
 *
 * File: inc/blocks/Support/VideoEmbed.php
 */

declare(strict_types=1);

namespace Blocks\Support;

final class VideoEmbed
{
    /**
     * @return string|null Embed URL for YouTube/Vimeo input, null otherwise.
     */
    public static function fromInput(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        // Pasted <iframe …> markup: use its src (entities decoded, e.g. &amp;).
        if (preg_match('/src=["\']([^"\']+)["\']/i', $input, $m)) {
            $input = html_entity_decode($m[1], ENT_QUOTES);
        }

        // YouTube: watch?v=ID, youtu.be/ID, /shorts/ID, /live/ID, /embed/ID (11-char IDs).
        if (preg_match('#(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|shorts/|live/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $input, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        // Vimeo: vimeo.com/ID, /video/ID, player.vimeo.com/video/ID; unlisted videos carry a hash as /ID/HASH or ?h=HASH.
        if (preg_match('#vimeo\.com/(?:video/)?(\d+)(?:/([a-f0-9]+))?#i', $input, $m)) {
            $url  = 'https://player.vimeo.com/video/' . $m[1];
            $hash = $m[2] ?? '';

            if ($hash === '' && preg_match('#[?&]h=([a-f0-9]+)#i', $input, $h)) {
                $hash = $h[1];
            }

            return $hash !== '' ? add_query_arg('h', $hash, $url) : $url;
        }

        return null;
    }

    /** Player URL that starts playing on load — only for user-initiated (facade click) embeds. */
    public static function withAutoplay(string $embed_url): string
    {
        return add_query_arg('autoplay', '1', $embed_url);
    }
}
