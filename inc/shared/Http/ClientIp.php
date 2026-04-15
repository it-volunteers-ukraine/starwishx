<?php

declare(strict_types=1);

namespace Shared\Http;

/**
 * Client IP resolution with opt-in proxy awareness.
 *
 * Defaults to $_SERVER['REMOTE_ADDR'] — the only always-accurate source for
 * direct connections. Forwarded-for headers (X-Forwarded-For, CF-Connecting-IP,
 * etc.) are trivially spoofable on direct connections, so this helper does NOT
 * honor them by default.
 *
 * Sites behind a trusted proxy (Cloudflare, nginx upstream, LB) can override
 * via the `starwishx/client_ip` filter:
 *
 *     add_filter('starwishx/client_ip', function ($default) {
 *         return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $default;
 *     });
 */
final class ClientIp
{
    public static function resolve(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        return (string) apply_filters('starwishx/client_ip', $ip);
    }
}
