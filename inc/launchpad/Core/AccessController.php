<?php

declare(strict_types=1);

namespace Launchpad\Core;

class AccessController
{

    private const LAUNCHPAD_ROLES = [
        'subscriber',
        'contributor',
        'author',
    ];

    public function init(): void
    {
        add_action('admin_init', [$this, 'blockAdminAccess']);
        add_action('wp_loaded', [$this, 'hideAdminBar']);
        add_filter('login_redirect', [$this, 'redirectAfterLogin'], 10, 3);
        add_action('admin_init', [$this, 'redirectProfileToLaunchpad']);
    }

    public function shouldUseLaunchpad(?int $userId = null): bool
    {
        $user = $userId ? get_userdata($userId) : wp_get_current_user();

        if (!$user || !$user->exists()) {
            return false;
        }

        $user_roles = $user->roles;
        $has_admin_role = array_intersect($user_roles, ['administrator', 'editor']);

        if (!empty($has_admin_role)) {
            return false;
        }

        return !empty(array_intersect($user_roles, self::LAUNCHPAD_ROLES));
    }

    public function blockAdminAccess(): void
    {
        if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if ($this->shouldUseLaunchpad()) {
            wp_redirect(home_url('/launchpad/'));
            exit;
        }
    }

    public function hideAdminBar(): void
    {
        if ($this->shouldUseLaunchpad()) {
            show_admin_bar(false);
        }
    }

    public function redirectAfterLogin(string $redirect_to, string $requested, $user): string
    {
        if (is_wp_error($user) || !$user) {
            return $redirect_to;
        }

        if ($this->shouldUseLaunchpad($user->ID)) {
            return home_url('/launchpad/');
        }

        return $redirect_to;
    }

    public function redirectProfileToLaunchpad(): void
    {
        global $pagenow;

        if ($pagenow === 'profile.php' && $this->shouldUseLaunchpad()) {
            wp_redirect(home_url('/launchpad/?panel=profile'));
            exit;
        }
    }

    public static function getLaunchpadUrl(string $panel = ''): string
    {
        $url = home_url('/launchpad/');
        return $panel ? add_query_arg('panel', $panel, $url) : $url;
    }
}
