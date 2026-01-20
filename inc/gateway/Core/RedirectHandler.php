<?php
// File: Gateway\Core\RedirectHandler.php

declare(strict_types=1);

namespace Gateway\Core;

/**
 * Intercepts WordPress authentication URLs and redirects to Gateway.
 *
 * This class handles the delicate task of hijacking wp-login.php
 * while preserving functionality that MUST go through native WP.
 */
class RedirectHandler
{
    /**
     * Actions that must be allowed through to native wp-login.php
     */
    // private const PASSTHROUGH_ACTIONS = [
    //     'logout',           // Logout must use WP's logout logic for cookies
    //     'postpass',         // Password-protected posts
    //     'confirmaction',    // Email confirmation actions
    //     'login_link_m',     // Magic link login plugins
    // ];

    /**
     * Actions the Gateway is explicitly designed to handle.
     */
    private const SUPPORTED_ACTIONS = [
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
        if ((defined('DOING_AJAX') && DOING_AJAX) || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) {
            return;
        }

        // 2. Determine the current action
        $action = $_REQUEST['action'] ?? 'login'; // wp-login.php defaults to login

        // 3. Extensibility: Allow other modules to register actions they want Gateway to handle
        $supported_actions = apply_filters('gateway_supported_actions', self::SUPPORTED_ACTIONS);

        // 4. THE CRITICAL CHANGE: Only intercept if we explicitly support the action
        if (!in_array($action, $supported_actions, true)) {
            return; // Let WordPress handle it (2FA, Social Login, Logout, etc.)
        }

        // 5. Special Case: If it's a POST request to wp-login.php, 
        // it's likely a native form submission or a plugin hook. 
        // DON'T redirect POSTs or you break form processing.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return;
        }

        // 6. Map and Redirect
        $redirect = match ($action) {
            'register'        => $this->buildGatewayUrl('register'),
            'lostpassword',
            'retrievepassword' => $this->buildGatewayUrl('forgot-password'),
            'rp',
            'resetpass'       => $this->buildResetUrl(),
            default           => $this->buildGatewayUrl('login'),
        };

        if (!empty($_GET['redirect_to'])) {
            $redirect = add_query_arg('redirect_to', urlencode($_GET['redirect_to']), $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Build Gateway URL with view parameter.
     */
    private function buildGatewayUrl(string $view = 'login'): string
    {
        $base = home_url('/gateway/');
        return $view === 'login' ? $base : add_query_arg('view', $view, $base);
    }

    /**
     * Build password reset URL preserving WP's token parameters.
     */
    private function buildResetUrl(): string
    {
        $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
        $login = isset($_GET['login']) ? sanitize_user($_GET['login']) : '';

        return add_query_arg([
            'view'  => 'reset-password',
            'key'   => $key,
            'login' => rawurlencode($login),
        ], home_url('/gateway/'));
    }

    /**
     * Filter: login_url
     *
     * Override wp_login_url() to return Gateway URL.
     */
    public function filterLoginUrl(string $url, string $redirect = ''): string
    {
        $gateway = home_url('/gateway/');

        if ($redirect) {
            $gateway = add_query_arg('redirect_to', urlencode($redirect), $gateway);
        }

        return $gateway;
    }

    /**
     * Filter: register_url
     */
    public function filterRegisterUrl(string $url): string
    {
        return home_url('/gateway/?view=register');
    }

    /**
     * Filter: lostpassword_url
     */
    public function filterLostPasswordUrl(string $url): string
    {
        return home_url('/gateway/?view=forgot-password');
    }

    /**
     * Filter: logout_url
     *
     * We keep logout going through wp-login.php?action=logout
     * but redirect after to Gateway instead of wp-login.php
     */
    // public function filterLogoutUrl(string $url, string $redirect = ''): string
    // {
    //     $logout_redirect = $redirect ?: home_url('/gateway/');

    //     // Build the logout URL manually instead of calling wp_logout_url()
    //     $logout_url = wp_nonce_url(
    //         add_query_arg('action', 'logout', wp_login_url()),
    //         'log-out'
    //     );

    //     // Add redirect parameter if provided
    //     if ($logout_redirect) {
    //         $logout_url = add_query_arg('redirect_to', urlencode($logout_redirect), $logout_url);
    //     }

    //     return $logout_url;
    // }
    
    // private static $inFilter = false;
    // public function filterLogoutUrl(string $url, string $redirect = ''): string
    // {
    //     if (self::$inFilter) {
    //         return $url; // Prevent infinite recursion
    //     }

    //     self::$inFilter = true;
    //     $result = wp_logout_url($redirect ?: home_url('/gateway/'));
    //     self::$inFilter = false;

    //     return $result;
    // }

    public function filterLogoutUrl(string $url, string $redirect = ''): string
    {
        // Manually build the logout URL to bypass other filters
        $args = [
            'action' => 'logout',
            '_wpnonce' => wp_create_nonce('log-out'),
        ];

        if ($redirect) {
            $args['redirect_to'] = urlencode($redirect);
        } else {
            $args['redirect_to'] = urlencode(home_url('/gateway/'));
        }

        return add_query_arg($args, site_url('wp-login.php', 'login'));
    }
}
