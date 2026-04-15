<?php

declare(strict_types=1);

namespace Shared\Http;

/**
 * Client IP resolution with opt-in trusted-proxy support.
 *
 * Defaults to $_SERVER['REMOTE_ADDR'] — the only always-accurate source for
 * direct connections. Forwarded-for headers (X-Forwarded-For, CF-Connecting-IP,
 * etc.) are trivially spoofable on direct connections and are honored ONLY
 * when the operator explicitly opts in.
 *
 * OPERATOR SETUP — pick the preset matching your infra in wp-config.php:
 *
 *     Direct origin (default — safe, no config needed):
 *     nothing to do
 *
 *     Behind Cloudflare:
 *     define('STARWISHX_TRUSTED_PROXY', 'cloudflare');
 *
 *     Behind nginx / ALB / standard reverse proxy:
 *     define('STARWISHX_TRUSTED_PROXY', 'standard');
 *
 *     Custom header (Akamai True-Client-IP, Fastly, etc.) — pass the raw
 *     $_SERVER key:
 *     define('STARWISHX_TRUSTED_PROXY', 'HTTP_TRUE_CLIENT_IP');
 *
 * SECURITY MODEL — defining the constant is an operator attestation that the
 * site ONLY accepts traffic through the named proxy. If the origin is reachable
 * directly (bypassing the proxy), an attacker can forge the forwarded header
 * and evade rate limiting. Firewall the origin to the proxy's published IP
 * ranges to close that gap; this class does not verify proxy origin itself.
 *
 * The `starwishx/client_ip` filter runs after the preset and can override for
 * advanced cases (CIDR-verified Cloudflare, custom proxy chains, etc.).
 */
final class ClientIp
{
    /** Read REMOTE_ADDR directly. Default when the constant is unset. */
    public const PROXY_NONE = 'none';

    /** Trust CF-Connecting-IP. Use behind Cloudflare. */
    public const PROXY_CLOUDFLARE = 'cloudflare';

    /** Trust first entry in X-Forwarded-For. Use behind nginx / ALB / standard LB. */
    public const PROXY_STANDARD = 'standard';

    public static function resolve(): string
    {
        $default = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $ip = self::resolveByMode($default);

        return (string) apply_filters('starwishx/client_ip', $ip);
    }

    private static function resolveByMode(string $default): string
    {
        if (!defined('STARWISHX_TRUSTED_PROXY')) {
            return $default;
        }

        $mode = (string) constant('STARWISHX_TRUSTED_PROXY');

        return match ($mode) {
            self::PROXY_NONE       => $default,
            self::PROXY_CLOUDFLARE => (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $default),
            self::PROXY_STANDARD   => self::firstForwardedFor($default),
            // Any other value is treated as a raw $_SERVER key so vendors
            // beyond the built-in presets (Akamai, Fastly, custom LBs) work
            // without a code change here.
            default => (string) ($_SERVER[$mode] ?? $default),
        };
    }

    private static function firstForwardedFor(string $default): string
    {
        $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff === '') {
            return $default;
        }

        $first = trim(explode(',', $xff)[0] ?? '');
        return $first !== '' ? $first : $default;
    }
}
