<?php

/**
 * ManagedPage - pages the theme owns
 *
 * File: inc/shared/Core/ManagedPage.php
 */

declare(strict_types=1);

namespace Shared\Core;

use WP_Post;

/**
 * A page the theme creates once and refuses to let anyone delete.
 *
 * Replaces three hand-rolled copies of the same logic (Gateway, Launchpad,
 * Listing), each of which had the same two weaknesses:
 *
 *   1. The slug it looked up and the slug it created were separate literals,
 *      free to drift apart. Listing's had: it searched for "opportunities" and
 *      inserted "listing", so every activation added another page and
 *      wp_unique_post_slug() renamed it listing-1, listing-2, and so on. Here
 *      one 'slug' key feeds both, so the two cannot disagree.
 *
 *   2. Deletion was blocked with wp_die(), which white-screens the block editor
 *      and list-table bulk actions. Withholding the capability is quieter and
 *      lands in every surface at once.
 */
final class ManagedPage
{
    /**
     * Bump when a spec changes so the admin_init pass re-runs once per deploy.
     */
    private const VERSION = '1';

    /** @var array<string, array<string, mixed>> Keyed by option name. */
    private static array $specs = [];

    private static bool $hooked = false;

    /**
     * Declare a managed page. Safe to call on every request - it only records
     * the spec and attaches hooks; nothing touches the database until
     * after_switch_theme or the version-gated admin_init pass fires.
     *
     * @param array $spec {
     *     @type string   $option        Option name holding the page ID. Required.
     *     @type string   $slug          Page slug, used to look up *and* create. Required.
     *     @type callable $title         Returns the page title. A callable, not a
     *                                   string: __() at file-load time runs before
     *                                   load_theme_textdomain() and trips the
     *                                   _doing_it_wrong notice WP 6.7 added.
     *     @type string   $template      Page template path, or '' for none.
     *     @type string   $legacy_option Former option name to adopt and retire.
     * }
     */
    public static function register(array $spec): void
    {
        self::$specs[$spec['option']] = $spec + [
            'template'      => '',
            'legacy_option' => '',
        ];

        if (self::$hooked) {
            return;
        }

        self::$hooked = true;

        add_action('after_switch_theme', [self::class, 'ensureAll']);
        add_action('admin_init',         [self::class, 'maybeEnsureAll']);

        // Capability layer: hides Trash in the list table and the editor, and
        // makes REST DELETE answer 403.
        add_filter('map_meta_cap', [self::class, 'denyDeleteCaps'], 10, 4);

        // Last-ditch layer: wp_delete_post() and wp_trash_post() check no
        // capabilities at all, so a plugin calling them directly would sail
        // past the filter above. Returning non-null short-circuits both.
        add_filter('pre_delete_post', [self::class, 'blockDelete'], 10, 2);
        add_filter('pre_trash_post',  [self::class, 'blockTrash'],  10, 2);
    }

    /**
     * Create or adopt every registered page.
     */
    public static function ensureAll(): void
    {
        foreach (self::$specs as $spec) {
            self::ensure($spec);
        }
    }

    /**
     * Catch deployments that never switched the theme - a Git pull or an FTP
     * upload leaves after_switch_theme unfired, so the pages would never exist.
     * Gated on a version option (autoloaded, so this costs one cache read per
     * admin request) rather than checking each page every time.
     */
    public static function maybeEnsureAll(): void
    {
        if (get_option('sw_managed_pages_version') === self::VERSION) {
            return;
        }

        self::ensureAll();
        update_option('sw_managed_pages_version', self::VERSION, false);
    }

    /**
     * Resolve the page for one spec, creating it only as a last resort.
     *
     * @return int Page ID, or 0 if creation failed.
     */
    private static function ensure(array $spec): int
    {
        // 1. The stored ID wins. Someone may have renamed the slug in wp-admin;
        //    that is their call to make, and we should not treat it as "missing"
        //    and bolt a second page alongside it.
        $stored = (int) get_option($spec['option']);
        if ($stored && self::isUsablePage($stored)) {
            self::reconcile($stored, $spec);

            return $stored;
        }

        // 2. Adopt a page already sitting at the slug. This is what lets an
        //    existing install pick up its page with no migration step.
        $existing = get_page_by_path($spec['slug'], OBJECT, 'page');
        if ($existing instanceof WP_Post) {
            update_option($spec['option'], $existing->ID, false);
            self::reconcile($existing->ID, $spec);

            return $existing->ID;
        }

        // 3. Adopt from the previous option name, then retire it.
        if ($spec['legacy_option'] !== '') {
            $legacy = (int) get_option($spec['legacy_option']);
            if ($legacy && self::isUsablePage($legacy)) {
                update_option($spec['option'], $legacy, false);
                delete_option($spec['legacy_option']);
                self::reconcile($legacy, $spec);

                return $legacy;
            }

            delete_option($spec['legacy_option']);
        }

        return self::create($spec);
    }

    /**
     * Insert the page. post_name matches the slug step 2 searches for, which is
     * the whole point of routing both through one key.
     */
    private static function create(array $spec): int
    {
        $postarr = [
            'post_title'   => (string) call_user_func($spec['title']),
            'post_name'    => $spec['slug'],
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
            // The admin performing the switch, falling back to the first user
            // for WP-CLI and other contexts with no current user.
            'post_author'  => get_current_user_id() ?: 1,
        ];

        if ($spec['template'] !== '') {
            $postarr['meta_input'] = ['_wp_page_template' => $spec['template']];
        }

        $page_id = wp_insert_post($postarr, true);

        if (is_wp_error($page_id)) {
            return 0;
        }

        update_option($spec['option'], (int) $page_id, false);

        return (int) $page_id;
    }

    /**
     * Bring an adopted page back in line with its spec.
     *
     * The spec is the authority on the template, so a page that outlived the
     * template it was created with - as the Opportunities page did when the
     * archive took over its URL - stops pointing at a file that is no longer
     * there. Everything else about the page is left to whoever edits it.
     */
    private static function reconcile(int $post_id, array $spec): void
    {
        self::restoreIfTrashed($post_id);

        $current = (string) get_post_meta($post_id, '_wp_page_template', true);

        if ($spec['template'] === '') {
            if ($current !== '' && $current !== 'default') {
                delete_post_meta($post_id, '_wp_page_template');
            }

            return;
        }

        if ($current !== $spec['template']) {
            update_post_meta($post_id, '_wp_page_template', $spec['template']);
        }
    }

    /**
     * A page that was trashed before this code ran is brought back rather than
     * duplicated - the page is meant to be undeletable, so honouring a stale
     * trash would defeat the point.
     */
    private static function restoreIfTrashed(int $post_id): void
    {
        if (get_post_status($post_id) === 'trash') {
            wp_untrash_post($post_id);
            wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
        }
    }

    private static function isUsablePage(int $post_id): bool
    {
        $post = get_post($post_id);

        return $post instanceof WP_Post && $post->post_type === 'page';
    }

    /**
     * Deny delete_post on a managed page.
     *
     * @param string[] $caps    Primitive capabilities required.
     * @param string   $cap     Capability being checked.
     * @param int      $user_id User under test.
     * @param array    $args    $args[0] is the post ID for meta capabilities.
     *
     * @return string[]
     */
    public static function denyDeleteCaps(array $caps, string $cap, int $user_id, array $args): array
    {
        if ($cap !== 'delete_post' || empty($args[0])) {
            return $caps;
        }

        return self::isLocked((int) $args[0]) ? ['do_not_allow'] : $caps;
    }

    /**
     * @param null|bool $check Non-null cancels wp_delete_post().
     *
     * @return null|bool
     */
    public static function blockDelete($check, WP_Post $post)
    {
        return self::isLocked($post->ID) ? false : $check;
    }

    /**
     * @param null|bool $check Non-null cancels wp_trash_post().
     *
     * @return null|bool
     */
    public static function blockTrash($check, WP_Post $post)
    {
        return self::isLocked($post->ID) ? false : $check;
    }

    /**
     * Is this post one of ours, and still locked?
     *
     * The 'sw_managed_page_locked' filter is the way out for anyone who
     * genuinely needs to remove one - returning false there restores normal
     * WordPress behaviour for that post.
     */
    private static function isLocked(int $post_id): bool
    {
        $managed = false;

        foreach (self::$specs as $spec) {
            if ($post_id === (int) get_option($spec['option'])) {
                $managed = true;
                break;
            }
        }

        if (!$managed) {
            return false;
        }

        return (bool) apply_filters('sw_managed_page_locked', true, $post_id);
    }
}
