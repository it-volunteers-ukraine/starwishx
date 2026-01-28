<?php

/** 
 * Helpers functions
 * 
 * File: inc/theme-helpers.php
 */

declare(strict_types=1);

if (!defined('ABSPATH')) exit;

/**
 * Check if string contains anything looking like a URL/Domain
 * dot inclide. more aggressive against bots.
 */
function sw_contains_url(string $text): bool
{
    // Checks for http/https OR for strings like "example.com" or "bit.ly"
    return (bool) preg_match('#(https?://|www\.)#i', $text) || 
           (bool) preg_match('#\b[a-z0-0-]+(\.[a-z]{2,})+(/\S*)?\b#i', $text);
}

function sw_strip_urls(string $text): string
{
    if (! is_string($text) || trim($text) === '') {
        return '';
    }

    // Remove full protocol URLs (http/https/ftp/sftp/ftps + path/query/fragment/port/auth)
    $text = preg_replace('#\b(?:https?|ftp|sftp|ftps)://[^\s<>"\']+#i', '', $text);

    // Remove www. domains (requires at least one dot after www + TLD-like structure)
    $text = preg_replace('#\bwww\.[^\s<>"\']+\.[^\s<>"\']+#i', '', $text);

    return $text;
}

function sw_sanitize_text_field(string $input): string
{
    if (! is_string($input)) {
        return '';
    }

    $text = sanitize_text_field($input);

    $text = sw_strip_urls($text);

    // Normalize whitespace (multiple spaces/tabs → single space) and trim
    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}

function sw_sanitize_textarea_field(string $input): string
{
    if (! is_string($input)) {
        return '';
    }

    $text = sanitize_textarea_field($input);

    $text = sw_strip_urls($text);

    // Optional light cleanup: normalize horizontal whitespace only (no touching newlines)
    // This prevents "   " becoming one space inside paragraphs but keeps line breaks intact
    $text = preg_replace('/[ \t]+/', ' ', $text);

    return trim($text);
}
