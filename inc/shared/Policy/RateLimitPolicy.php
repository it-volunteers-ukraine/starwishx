<?php

/**
 * Rate limiting policy — transient-based sliding window.
 *
 * Stateless static utility following the PhonePolicy / PasswordPolicy pattern.
 * Uses WordPress transients for storage — works with or without an object cache.
 *
 * Two usage patterns:
 *
 *   Unconditional (contact form, password reset) — every attempt counts:
 *     $key = RateLimitPolicy::key('contact', $ip);
 *     $check = RateLimitPolicy::check($key, 5, HOUR_IN_SECONDS);
 *     if (is_wp_error($check)) return $check;
 *     // ... process ...
 *     RateLimitPolicy::hit($key, HOUR_IN_SECONDS);
 *
 *   Conditional (auth) — only failures count:
 *     $key = RateLimitPolicy::key('login', $username, $ip);
 *     $check = RateLimitPolicy::check($key, 5, 900);
 *     if (is_wp_error($check)) return $check;
 *     // ... try auth ...
 *     if ($failed) RateLimitPolicy::hit($key, 900);
 *     else         RateLimitPolicy::clear($key);
 *
 * File: inc/shared/Policy/RateLimitPolicy.php
 */

declare(strict_types=1);

namespace Shared\Policy;

use WP_Error;

final class RateLimitPolicy
{
    /**
     * Check whether the rate limit has been exceeded.
     *
     * @param string $key          Transient key (use self::key() to build).
     * @param int    $maxAttempts  Maximum allowed attempts within the window.
     * @param int    $windowSec    Window duration in seconds.
     * @param string $message      Optional custom error message.
     * @return true|WP_Error       True if under limit, WP_Error if exceeded.
     */
    public static function check(
        string $key,
        int $maxAttempts,
        int $windowSec,
        string $message = ''
    ): bool|WP_Error {
        $attempts = (int) get_transient($key);

        if ($attempts >= $maxAttempts) {
            if ($message === '') {
                $minutes = max(1, (int) ceil($windowSec / 60));
                $message = sprintf(
                    /* translators: %d: minutes until rate limit resets */
                    __('Too many attempts. Please try again in %d minutes.', 'starwishx'),
                    $minutes
                );
            }

            return new WP_Error('rate_limited', $message);
        }

        return true;
    }

    /**
     * Increment the attempt counter.
     *
     * @param string $key       Transient key.
     * @param int    $windowSec Window duration in seconds (resets TTL on each hit).
     */
    public static function hit(string $key, int $windowSec): void
    {
        $attempts = (int) get_transient($key);
        set_transient($key, $attempts + 1, $windowSec);
    }

    /**
     * Clear the attempt counter (e.g., on successful authentication).
     *
     * @param string $key Transient key.
     */
    public static function clear(string $key): void
    {
        delete_transient($key);
    }

    /**
     * Build a deterministic, safe transient key from an action and identifier parts.
     *
     * Output: 'rl_' + md5(action|part1|part2|...) = 35 chars,
     * well under WordPress's 172-char transient key limit.
     *
     * @param string $action Action name (e.g., 'contact', 'login', 'pwd_reset').
     * @param string ...$parts Identifier parts (e.g., IP, username, email).
     */
    public static function key(string $action, string ...$parts): string
    {
        return 'rl_' . md5($action . '|' . implode('|', $parts));
    }
}
