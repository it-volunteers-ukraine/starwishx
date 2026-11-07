<?php

/**
 * URL validation policy on PHP's native parser (Uri\WhatWg\Url).
 *
 * Normalizes input (auto-prepend https://) and validates format.
 * Only http and https schemes are allowed; IP hosts are rejected in any
 * spelling.
 *
 * The parser follows browsers, so a URL is judged by what a browser would
 * open. It is also as forgiving as a browser, and a stored link shouldn't be:
 * the checks here take that back.
 *
 * tests/url-policy.php pins the behaviour. The parser ships with PHP, so a PHP
 * update can change what it accepts without anything in the theme changing.
 *
 * File: inc/shared/Policy/UrlPolicy.php
 */

declare(strict_types=1);

namespace Shared\Policy;

use Uri\WhatWg\Url;
use Uri\WhatWg\UrlValidationErrorType;
use WP_Error;

final class UrlPolicy
{
    /** Browsers drop or rewrite these, so they would open a different URL. */
    private const UNSAFE_CHARS = '/[\x00-\x1F\x7F\\\\]/';

    /**
     * Strict IDNA2008. Browsers are lenient with international hosts, and an
     * invisible character can make a different domain that looks the same.
     */
    private const IDNA_OPTIONS = IDNA_NONTRANSITIONAL_TO_ASCII
        | IDNA_CHECK_BIDI
        | IDNA_USE_STD3_RULES
        | IDNA_CHECK_CONTEXTJ;

    /**
     * Normalize and validate a URL string.
     *
     * - Empty input is valid (field optionality is the caller's concern).
     * - Auto-prepends https:// when no scheme is present.
     * - Only http/https schemes are allowed.
     * - Host must be a valid domain name (rejects IPs, leading/trailing hyphens).
     *
     * @param string|null $url Raw user input.
     * @return string|WP_Error Normalized URL, or WP_Error on failure.
     */
    public static function validate(?string $url): string|WP_Error
    {
        if ($url === null || $url === '') {
            return '';
        }

        $raw = trim($url);
        if ($raw === '') {
            return '';
        }

        // Auto-prepend scheme for bare domains (google.com, google.com:8080)
        // and protocol-relative URLs (//example.com)
        if (str_starts_with($raw, '//')) {
            $raw = 'https:' . $raw;
        } elseif (!preg_match('#^[a-zA-Z][a-zA-Z0-9+\-.]*://#', $raw)) {
            $raw = 'https://' . $raw;
        }

        if (preg_match(self::UNSAFE_CHARS, $raw)) {
            return self::error();
        }

        $errors = [];
        $uri = Url::parse($raw, null, $errors);
        if ($uri === null || self::isForgiven($errors)) {
            return self::error();
        }

        $scheme = $uri->getScheme();
        if ($scheme !== 'http' && $scheme !== 'https') {
            return self::error();
        }

        $host = (string) $uri->getAsciiHost();
        if (!self::isDomain($host) || !self::matchesTypedHost($raw, $host)) {
            return self::error();
        }

        // The parser always writes a path; a bare domain stays as it was typed.
        $normalized = $uri->toAsciiString();
        return $uri->getPath() === '/' && !str_ends_with($raw, '/')
            ? rtrim($normalized, '/')
            : $normalized;
    }

    /**
     * What the parser repairs but a stored link shouldn't get away with:
     * userinfo, a phishing vector (https://google.com@evil.com) even when
     * empty, and the wrong number of slashes after the scheme.
     *
     * @param array<\Uri\WhatWg\UrlValidationError> $errors The parser's soft errors.
     */
    private static function isForgiven(array $errors): bool
    {
        foreach ($errors as $error) {
            if (
                $error->type === UrlValidationErrorType::InvalidCredentials
                || $error->type === UrlValidationErrorType::SpecialSchemeMissingFollowingSolidus
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rejects IPs, labels starting/ending with hyphens, etc. The parser has
     * already turned 127.1 and 2130706433 into 127.0.0.1, so they count as IPs.
     */
    private static function isDomain(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP) === false
            && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    /**
     * The host as typed must be what the parser made of it: not percent-encoded
     * (an obfuscation trick) and, when international, accepted by the strict
     * rules with the same result.
     */
    private static function matchesTypedHost(string $raw, string $host): bool
    {
        preg_match('#^[^:]+://([^/?\#:]*)#', $raw, $match);
        $typed = $match[1] ?? '';

        if (str_contains($typed, '%')) {
            return false;
        }
        if (!preg_match('/[^\x00-\x7F]/', $typed)) {
            return true;
        }

        $info = [];
        $ascii = idn_to_ascii($typed, self::IDNA_OPTIONS, INTL_IDNA_VARIANT_UTS46, $info);

        return $ascii === $host && ($info['errors'] ?? 0) === 0;
    }

    private static function error(): WP_Error
    {
        return new WP_Error(
            'url_invalid',
            __('Please enter a valid URL (e.g. https://example.com).', 'starwishx')
        );
    }
}
