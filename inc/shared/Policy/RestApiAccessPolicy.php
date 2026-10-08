<?php
// File: inc/shared/Policy/RestApiAccessPolicy.php
declare(strict_types=1);

namespace Shared\Policy;

/**
 * Core REST API routes reserved for editorial staff (BackOfficePolicy).
 *
 * Pure data — no WP hooks. Consumed by Shared\Http\RestApiGate, which strips
 * matching routes from the registry for everyone below that threshold —
 * guests and Launchpad users (subscribers, contributors) — so the controller
 * responds 404 (no auth fingerprint, no signal that the route exists).
 *
 * Defaults target the well-known enumeration vectors:
 *   - /wp/v2/users      — username/ID enumeration (OWASP A01)
 *   - /wp/v2/media      — uploaded-file inventory
 *   - /wp/v2/comments   — superseded by inc/comments module
 *   - /wp/v2/{types,taxonomies,statuses,search} — site topology recon
 *
 * Launchpad users are gated like guests. The app talks only to the theme's
 * own namespaces (launchpad/v1, favorites/v1, comments/v1, …) and never needs
 * these routes — but every front-end module hydrates a `wp_rest` nonce, and
 * that nonce authenticates /wp/v2 too. Open to contributors, the gate would
 * let any of them list every user who can author posts through
 * `/wp/v2/users?who=authors`: core serves that to anyone with `edit_posts`
 * and skips its "has published posts" restriction for it.
 *
 * "Guest" in the method and filter names means "below BackOfficePolicy"; the
 * names stay because the hooks are a public contract.
 *
 * Extend via the `starwishx/rest_guest_denied_routes` filter (e.g. to add
 * routes from third-party plugins or carve exceptions for headless clients).
 */
final class RestApiAccessPolicy
{
    /**
     * Whether the current user may use the gated routes: editorial staff
     * only (BackOfficePolicy — editors and administrators).
     *
     * They need them because the block editor boots from these routes, not
     * just its author dropdown: wp-admin preloads /wp/v2/types,
     * /wp/v2/taxonomies, /wp/v2/types/{type} and /wp/v2/users/me, and
     * core-data reads the post-type entity config from /wp/v2/types. The
     * preloads pass through the same `rest_endpoints` filter, so a stripped
     * route surfaces as "You attempted to edit an item that doesn't exist" on
     * every show_in_rest post type (news, project, ngo, post, page).
     *
     * Override via the `starwishx/rest_gate_bypass` filter (e.g. to admit an
     * integration's service account). Anything narrower than BackOfficePolicy
     * breaks the block editor for the users it leaves out.
     */
    public static function isPrivileged(): bool
    {
        return (bool) apply_filters(
            'starwishx/rest_gate_bypass',
            BackOfficePolicy::allows()
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
