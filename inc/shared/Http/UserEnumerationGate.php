<?php
// File: inc/shared/Http/UserEnumerationGate.php
declare(strict_types=1);

namespace Shared\Http;

use Shared\Policy\RestApiAccessPolicy;

/**
 * Closes the front-end user enumeration vectors that `RestApiGate` does not cover.
 *
 *  1. `?author=N` / `?author=username` — WordPress's redirect_canonical rewrites
 *     these to `/author/<slug>/`, leaking the username in the Location header.
 *     Force a 404 at template_redirect priority 1; redirect_canonical (priority
 *     10) bails on is_404(), so the slug never reaches the wire. Gated by
 *     RestApiAccessPolicy::isPrivileged() so editorial roles keep the legit
 *     redirect, while subscribers and guests are blocked.
 *
 *  2. `/wp-sitemap-users-1.xml` — drop the sitemap provider AND 404 the URL.
 *     The provider drop removes `users` from the `wp-sitemap.xml` index, but
 *     the rewrite rule for `wp-sitemap-*.xml` is generic and still matches
 *     `users-1` → `sitemap=users` query var. WP_Sitemaps::render_sitemaps()
 *     finds no provider, returns silently, and the request falls through to
 *     is_home() — rendering the default homepage (or the Hello-World sample
 *     post on a fresh install). The 404 here closes that fall-through. Applies
 *     to everyone — the URL genuinely does not exist.
 *
 * Pretty `/author/<slug>/` archives are intentionally left alone — that is a
 * feature decision, not a security one; knowing a slug to type is not
 * enumeration.
 */
final class UserEnumerationGate
{
    public static function boot(): void
    {
        add_action('template_redirect', [self::class, 'enforce'], 1);
        add_filter('wp_sitemaps_add_provider', [self::class, 'dropUsersSitemap'], 10, 2);
    }

    public static function enforce(): void
    {
        if (get_query_var('sitemap') === 'users') {
            self::send404();
            return;
        }

        if (isset($_GET['author']) && !RestApiAccessPolicy::isPrivileged()) {
            self::send404();
        }
    }

    private static function send404(): void
    {
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
