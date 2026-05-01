<?php
// File: inc/shared/Http/UserEnumerationGate.php
declare(strict_types=1);

namespace Shared\Http;

/**
 * Closes the front-end user enumeration vectors that `RestApiGate` does not cover.
 *
 *  1. `?author=N` / `?author=username` — WordPress's redirect_canonical rewrites
 *     these to `/author/<slug>/`, leaking the username in the Location header.
 *     Force a 404 at template_redirect priority 1; redirect_canonical (priority
 *     10) bails on is_404(), so the slug never reaches the wire.
 *
 *  2. `/wp-sitemap-users-1.xml` — WP 5.5+ ships a public users sitemap that
 *     defeats every other measure here. Drop the provider entirely.
 *
 * Both behaviors apply to guests only — logged-in users keep normal access.
 * Pretty `/author/<slug>/` archives are intentionally left alone (feature
 * decision, not security); knowing a slug to type is not enumeration.
 */
final class UserEnumerationGate
{
    public static function boot(): void
    {
        add_action('template_redirect', [self::class, 'blockAuthorQuery'], 1);
        add_filter('wp_sitemaps_add_provider', [self::class, 'dropUsersSitemap'], 10, 2);
    }

    public static function blockAuthorQuery(): void
    {
        if (is_user_logged_in()) {
            return;
        }

        if (!isset($_GET['author'])) {
            return;
        }

        global $wp_query;
        if ($wp_query instanceof \WP_Query) {
            $wp_query->set_404();
        }
        status_header(404);
        nocache_headers();
    }

    /**
     * @param mixed  $provider Sitemaps provider instance (or false if already dropped).
     * @param string $name     Provider slug — 'posts', 'taxonomies', 'users'.
     * @return mixed false to drop the provider; otherwise unchanged.
     */
    public static function dropUsersSitemap(mixed $provider, string $name): mixed
    {
        return $name === 'users' ? false : $provider;
    }
}
