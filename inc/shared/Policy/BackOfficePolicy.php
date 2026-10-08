<?php
// File: inc/shared/Policy/BackOfficePolicy.php
declare(strict_types=1);

namespace Shared\Policy;

/**
 * Who works in wp-admin — the single definition of "editorial staff".
 *
 * The site has two audiences:
 *   - editors and administrators moderate and publish in wp-admin (block
 *     editor, media library, the classic Opportunity screens);
 *   - subscribers and contributors live in the Launchpad app and never reach
 *     wp-admin — Launchpad\Core\AccessController redirects them to /launchpad/.
 *
 * Everything that must agree on that split asks this class, so wp-admin
 * access and core REST access cannot drift apart again:
 *   - RestApiAccessPolicy::isPrivileged() — the core /wp/v2 routes the block
 *     editor boots from (RestApiGate) and the ?author= redirect
 *     (UserEnumerationGate);
 *   - AccessController::shouldUseLaunchpad() — its editorial override.
 *
 * `edit_others_posts` is the capability that makes an Editor an Editor: it
 * matches exactly Editor + Administrator (and multisite super admins) without
 * naming roles. Deliberately not a destructive capability such as
 * `delete_others_posts` — withholding that from editors is a reasonable
 * hardening that must not take the block editor down with it.
 *
 * Roles outside both audiences (Author, custom roles) are unsupported: they
 * reach wp-admin but get none of the gated core routes, so their block editor
 * fails. Place any new role on one side deliberately.
 *
 * Pure — no WP hooks.
 */
final class BackOfficePolicy
{
    public const CAPABILITY = 'edit_others_posts';

    /**
     * Whether the user (default: the current one) is editorial staff.
     */
    public static function allows(?\WP_User $user = null): bool
    {
        $user ??= wp_get_current_user();

        return $user->exists() && user_can($user, self::CAPABILITY);
    }
}
