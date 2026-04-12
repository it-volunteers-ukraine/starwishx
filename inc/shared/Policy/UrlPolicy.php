<?php

/**
 * URL validation policy using league/uri (RFC 3986).
 *
 * Normalizes input (auto-prepend https://) and validates format.
 * Only http and https schemes are allowed; bare IP hosts are rejected.
 *
 * File: inc/shared/Policy/UrlPolicy.php
 */

declare(strict_types=1);

namespace Shared\Policy;

use League\Uri\Contracts\UriException;
use League\Uri\Uri;
use WP_Error;

final class UrlPolicy
{
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

        try {
            $uri = Uri::new($raw);
        } catch (UriException) {
            return self::error();
        }

        $scheme = $uri->getScheme();
        if ($scheme !== 'http' && $scheme !== 'https') {
            return self::error();
        }

        // Reject userinfo — phishing vector (https://google.com@evil.com)
        // and catches mis-prepended mailto:/data: schemes
        if ($uri->getUserInfo() !== null) {
            return self::error();
        }

        // isDomainHost() rejects IPs, labels starting/ending with hyphens, etc.
        if (!$uri->isDomainHost()) {
            return self::error();
        }

        return $uri->toString();
    }

    private static function error(): WP_Error
    {
        return new WP_Error(
            'url_invalid',
            __('Please enter a valid URL (e.g. https://example.com).', 'starwishx')
        );
    }
}
