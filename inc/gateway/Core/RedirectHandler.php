<?php
// File: inc/gateway/Core/RedirectHandler.php

declare(strict_types=1);

namespace Gateway\Core;

use Gateway\Enums\GatewayView;

/**
 * Intercepts WordPress authentication URLs and redirects to Gateway.
 *
 * This class handles the delicate task of hijacking wp-login.php
 * while preserving functionality that MUST go through native WP
 * (logout, postpass, confirmaction, 2FA and social-login actions, POSTs).
 */
final class RedirectHandler
{
    /**
     * The wp-login.php actions the Gateway handles; every other action
     * passes through to WordPress.
     */
    private const array SUPPORTED_ACTIONS = [
        'login',
        'register',
        'lostpassword',
        'retrievepassword',
        'rp',
        'resetpass',
    ];

    /**
     * Hook: login_init
     *
     * This fires when wp-login.php is loaded, BEFORE any output.
     * We intercept here and redirect to Gateway for most actions.
     */
    public function interceptWpLogin(): void
    {
        // 1. Safety checks (AJAX, XML-RPC)
        if (wp_doing_ajax() || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) {
            return;
        }

        // 2. The action: wp-login.php defaults to login. Anything that isn't
        // a plain string isn't ours.
        $action = $_REQUEST['action'] ?? 'login';
        if (!is_string($action)) {
            return;
        }

        // 3. Extensibility: other modules may add actions for the Gateway.
        $supported = (array) apply_filters('gateway_supported_actions', self::SUPPORTED_ACTIONS);

        // 4. Only intercept actions we explicitly support.
        if (!in_array($action, $supported, true)) {
            return; // Let WordPress handle it (2FA, Social Login, Logout, etc.)
        }

        // 5. A POST to wp-login.php is a native form submission or a plugin
        // hook: redirecting it would break the form processing.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            return;
        }

        // 6. Map and redirect
        $view = match ($action) {
            'register'                       => GatewayView::REGISTER,
            'lostpassword', 'retrievepassword' => GatewayView::LOST_PASSWORD,
            'rp', 'resetpass'                => GatewayView::RESET_PASSWORD,
            default                          => GatewayView::LOGIN,
        };

        $redirect = $view === GatewayView::RESET_PASSWORD
            // Keep WP's token parameters for the reset form.
            ? GatewayView::resetUrl(
                sanitize_text_field(self::query('key')),
                sanitize_user(self::query('login'))
            )
            : $view->url();

        $redirectTo = self::query('redirect_to');
        if ($redirectTo !== '') {
            $redirect = add_query_arg('redirect_to', urlencode($redirectTo), $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * A query parameter as a string: '' when missing or not a string
     * (`?key[]=` must not reach a string function).
     */
    private static function query(string $name): string
    {
        $value = $_GET[$name] ?? '';
        return is_string($value) ? wp_unslash($value) : '';
    }

    /**
     * Filter: login_url
     *
     * Override wp_login_url() to return Gateway URL.
     */
    public function filterLoginUrl(string $url, ?string $redirect = ''): string
    {
        $gateway = GatewayView::LOGIN->url();

        return $redirect
            ? add_query_arg('redirect_to', urlencode($redirect), $gateway)
            : $gateway;
    }

    /**
     * Filter: register_url
     */
    public function filterRegisterUrl(string $url): string
    {
        return GatewayView::REGISTER->url();
    }

    /**
     * Filter: lostpassword_url
     */
    public function filterLostPasswordUrl(string $url): string
    {
        return GatewayView::LOST_PASSWORD->url();
    }

    /**
     * Filter: logout_url
     *
     * Logout keeps going through wp-login.php?action=logout (WordPress clears
     * the cookies), then lands on the Gateway instead of wp-login.php. Built
     * by hand: calling wp_logout_url() here would re-enter this filter.
     */
    public function filterLogoutUrl(string $url, ?string $redirect = ''): string
    {
        return add_query_arg([
            'action'      => 'logout',
            '_wpnonce'    => wp_create_nonce('log-out'),
            'redirect_to' => urlencode($redirect ?: GatewayView::LOGIN->url()),
        ], site_url('wp-login.php', 'login'));
    }
}
