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
 * get no signal that the route exists. Bypassed entirely for logged-in users
 * so the block editor and authenticated integrations keep working.
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
        if (is_user_logged_in()) {
            return $endpoints;
        }

        foreach (RestApiAccessPolicy::guestDeniedRoutes() as $route) {
            unset($endpoints[$route]);
        }

        return $endpoints;
    }
}
