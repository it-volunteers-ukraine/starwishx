<?php

/**
 * Input sanitization & URL-detection utilities.
 *
 * Static utility class — no state, no dependencies. All methods are pure
 * functions that transform or inspect a string value.
 *
 * File: inc/shared/Sanitize/InputSanitizer.php
 */

declare(strict_types=1);

namespace Shared\Sanitize;

final class InputSanitizer
{
    /**
     * Check if string contains anything looking like a URL/domain (dot included).
     * More aggressive against bots.
     */
    public static function containsUrl(string $text): bool
    {
        if (preg_match('#(https?://|www\.)#i', $text)) {
            return true;
        }

        return (bool) preg_match('#\b[a-z0-9-]+(\.[a-z]{2,})+(/\S*)?\b#i', $text);
    }

    /**
     * Strip URL-like patterns from a string, breaking bare domains with a space
     * so they no longer resolve as links or pass URL-detection checks.
     */
    public static function stripUrls(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        // Remove full protocol URLs (http/https/ftp/sftp/ftps + path/query/fragment/port/auth)
        $text = preg_replace('#\b(?:https?|ftp|sftp|ftps)://[^\s<>"\']+#i', '', $text);

        // Remove www. domains (requires at least one dot after www + TLD-like structure)
        $text = preg_replace('#\bwww\.[^\s<>"\']+\.[^\s<>"\']+#i', '', $text);

        // "The Dot Breaker"
        // Finds a dot between two letters/numbers that DOES NOT have a space after it.
        // Example: bit.ly -> bit. ly
        // Ignores numbers like 10.5 by checking for letters on the right side.
        $text = preg_replace('/([a-z0-9])\.([a-z]{2,})/i', '$1. $2', $text);

        return $text;
    }

    /**
     * Sanitize a URL field intended for web links (http/https only).
     *
     * - Auto-prepends https:// when the input has no scheme
     * - Rejects non-http(s) schemes (ftp, mailto, javascript, data, file…)
     * - Delegates final cleaning to WordPress esc_url_raw()
     *
     * Used as REST API sanitize_callback for sourcelink, application_form, userUrl.
     */
    public static function sanitizeUrl(?string $input): string
    {
        if ($input === null || trim($input) === '') {
            return '';
        }

        $url = trim($input);

        // If no scheme is present, assume https.
        // Matches "letters:" — covers http:, https:, ftp:, mailto:, javascript:, data:, etc.
        if (!preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $url)) {
            $url = 'https://' . $url;
        }

        // Delegate to WordPress core, restricted to web protocols.
        // esc_url_raw returns '' for disallowed schemes.
        return esc_url_raw($url, ['http', 'https']);
    }

    /**
     * Sanitize a single-line text value.
     *
     * Accepts ?string so callers can pass get_field() / get_post_meta() results
     * directly — both routinely return null on empty fields.
     *
     * NOTE: null/empty check avoids empty() which would swallow the valid string '0'.
     */
    public static function sanitizeText(?string $input): string
    {
        if ($input === null || $input === '') {
            return '';
        }

        $text = sanitize_text_field($input);
        $text = self::stripUrls($text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Sanitize a multi-line textarea value, preserving intentional line breaks.
     *
     * Accepts ?string — same rationale as sanitizeText above.
     */
    public static function sanitizeTextarea(?string $input): string
    {
        if ($input === null || $input === '') {
            return '';
        }

        $text = sanitize_textarea_field($input);
        $text = self::stripUrls($text);

        // Normalize horizontal whitespace only — newlines are intentional in textareas
        return trim((string) preg_replace('/[ \t]+/', ' ', $text));
    }
}
