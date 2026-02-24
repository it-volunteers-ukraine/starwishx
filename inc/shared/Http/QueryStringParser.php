<?php
// file: inc/Shared/Http/QueryStringParser.php
declare(strict_types=1);

namespace Shared\Http;

/**
 * Parses a raw query string with support for repeated keys without [] notation.
 *
 * PHP's built-in parse_str() and $_GET silently drop duplicate keys unless
 * they are suffixed with []. This parser preserves every occurrence:
 *   ?category=1&category=2  →  ['category' => ['1', '2']]
 *   ?page=3                 →  ['page'     => '3']
 *
 * Encapsulating $_SERVER access here keeps controllers free of superglobal
 * references and therefore fully unit-testable.
 */
class QueryStringParser
{
    /**
     * Parse a raw query string into an associative array.
     * Repeated keys are automatically promoted to indexed arrays.
     *
     * @param string $queryString  Raw query string, e.g. from $_SERVER['QUERY_STRING'].
     * @return array<string, string|string[]>
     */
    public static function parse(string $queryString): array
    {
        $parsed = [];

        foreach (explode('&', $queryString) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key   = urldecode($key);
            $value = urldecode($value);

            if (array_key_exists($key, $parsed)) {
                // Promote scalar to array on first collision, then keep appending
                $parsed[$key]   = (array) $parsed[$key];
                $parsed[$key][] = $value;
            } else {
                $parsed[$key] = $value;
            }
        }

        return $parsed;
    }

    /**
     * Convenience factory: parse directly from the current request's QUERY_STRING.
     *
     * @return array<string, string|string[]>
     */
    public static function fromServer(): array
    {
        return self::parse($_SERVER['QUERY_STRING'] ?? '');
    }
}
