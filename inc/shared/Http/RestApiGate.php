<?php
// File: inc/shared/Http/RestApiGate.php
declare(strict_types=1);

namespace Shared\Http;

use Shared\Policy\RestApiAccessPolicy;

/**
 * Strips guest-denied REST routes from the registry before dispatch.
 *
 * Uses the `rest_endpoints` filter so matched routes never enter the
 * dispatcher — controllers respond with a clean 404, not a 401, and scrapers
 * get no signal that the route exists. Privilege threshold lives in
 * RestApiAccessPolicy::isPrivileged(): editorial staff (BackOfficePolicy) keep
 * every route, so the block editor works; guests and Launchpad users
 * (subscribers, contributors) are gated.
 *
 * WP_REST_Server::get_routes() applies the filter on every call, including the
 * block editor's server-side preloads in wp-admin, so whatever is stripped here
 * is missing there too.
 */
final class RestApiGate
{
    public static function boot(): void
    {
        add_filter('rest_endpoints', [self::class, 'filterEndpoints']);
    }

    /**
     * @param array<string, mixed> $endpoints
     * @return array<string, mixed>
     */
    public static function filterEndpoints(array $endpoints): array
    {
        if (RestApiAccessPolicy::isPrivileged()) {
            return $endpoints;
        }

        foreach (RestApiAccessPolicy::guestDeniedRoutes() as $route) {
            unset($endpoints[$route]);
        }

        return $endpoints;
    }
}
