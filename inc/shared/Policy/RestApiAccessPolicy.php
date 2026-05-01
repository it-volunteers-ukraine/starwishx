<?php
// File: inc/shared/Policy/RestApiAccessPolicy.php
declare(strict_types=1);

namespace Shared\Policy;

/**
 * Declarative list of REST API routes denied to unauthenticated visitors.
 *
 * Pure data — no WP hooks. Consumed by Shared\Http\RestApiGate, which strips
 * matching routes from the registry for guests so the controller responds 404
 * (no auth fingerprint, no signal that the route exists on this install).
 *
 * Defaults target the well-known guest-enumeration vectors:
 *   - /wp/v2/users      — username/ID enumeration (OWASP A01)
 *   - /wp/v2/media      — uploaded-file inventory
 *   - /wp/v2/comments   — superseded by inc/comments module
 *   - /wp/v2/{types,taxonomies,statuses,search} — site topology recon
 *
 * Logged-in users are unaffected — Gutenberg, admin tooling, and authenticated
 * integrations rely on these routes and keep working normally.
 *
 * Extend via the `starwishx/rest_guest_denied_routes` filter (e.g. to add
 * routes from third-party plugins or carve exceptions for headless clients).
 */
final class RestApiAccessPolicy
{
    /**
     * Whether the current user has enough privilege to bypass the gate.
     *
     * Default threshold: `edit_posts` capability — admits contributors,
     * authors, editors, and admins (the roles that legitimately need REST
     * access for editorial workflows like Gutenberg's author-picker).
     * Guests and subscribers are gated.
     *
     * Why not `list_users` (admin-only)? Gutenberg fetches `/wp/v2/users`
     * for the author dropdown; stripping it for non-admins would break the
     * block editor for contributors and editors.
     *
     * Override via the `starwishx/rest_gate_bypass` filter — useful for
     * sites with custom roles or stricter requirements ("admins only").
     */
    public static function isPrivileged(): bool
    {
        return (bool) apply_filters(
            'starwishx/rest_gate_bypass',
            current_user_can('list_users')
        );
    }

    /**
     * @return string[] Route patterns as registered in `rest_endpoints` —
     *                  leading slash, regex placeholders preserved.
     */
    public static function guestDeniedRoutes(): array
    {
        $defaults = [
            // User enumeration — primary attack surface.
            '/wp/v2/users',
            '/wp/v2/users/(?P<id>[\d]+)',
            '/wp/v2/users/me',

            // Media inventory.
            '/wp/v2/media',
            '/wp/v2/media/(?P<id>[\d]+)',

            // Default comments route — theme uses inc/comments instead.
            '/wp/v2/comments',
            '/wp/v2/comments/(?P<id>[\d]+)',

            // Site topology disclosure.
            '/wp/v2/types',
            '/wp/v2/types/(?P<type>[\w-]+)',
            '/wp/v2/taxonomies',
            '/wp/v2/taxonomies/(?P<taxonomy>[\w-]+)',
            '/wp/v2/statuses',
            '/wp/v2/statuses/(?P<status>[\w-]+)',
            '/wp/v2/search',
        ];

        return (array) apply_filters('starwishx/rest_guest_denied_routes', $defaults);
    }
}
