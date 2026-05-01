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
 * RestApiAccessPolicy::isPrivileged() — defaults to editorial roles, so
 * Gutenberg keeps working while subscribers and guests are gated.
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
