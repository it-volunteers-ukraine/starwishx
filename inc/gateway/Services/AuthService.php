<?php
//File: inc/gateway/Services/AuthService.php

declare(strict_types=1);

namespace Gateway\Services;

use WP_Error;
use WP_User;

/**
 * Authentication service with rate limiting.
 *
 * Follows Launchpad's service pattern:
 * - Pure business logic, no HTTP concerns
 * - Returns typed data or WP_Error
 * - Accepts primitives, returns structured data
 */
class AuthService
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 15 * MINUTE_IN_SECONDS;

    /**
     * Authenticate user with rate limiting protection.
     *
     * @param string $username Username or email
     * @param string $password Plain text password
     * @param bool $remember Create persistent cookie
     * @return array{user: WP_User, redirect_to: string}|WP_Error
     */
    public function authenticate(
        string $username,
        string $password,
        bool $remember = false
    ): array|WP_Error {
        // Rate limiting check (by username + IP)
        if ($this->isRateLimited($username)) {
            return new WP_Error(
                'rate_limited',
                __('Too many login attempts. Please wait 15 minutes.', 'starwishx'),
                ['status' => 429]
            );
        }

        // Authenticate via WordPress
        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            $this->recordFailedAttempt($username);

            // Return generic error to prevent user enumeration
            return new WP_Error(
                'auth_failed',
                __('Invalid username or password.', 'starwishx'),
                ['status' => 401]
            );
        }

        // Success - clear rate limiting
        $this->clearAttempts($username);

        // Set authentication cookies
        wp_set_auth_cookie($user->ID, $remember);

        // Fire WordPress login action for compatibility
        do_action('wp_login', $user->user_login, $user);

        return [
            'user'        => $user,
            'redirect_to' => $this->determineRedirect($user),
        ];
    }

    /**
     * Logout current user.
     */
    public function logout(): void
    {
        wp_logout();
        wp_set_current_user(0);
    }


    /**
     * Determine post-login redirect.
     * 
     * Now strictly agnostic. It handles basic WP roles and 
     * defers specialized logic to external modules via filters.
     */
    private function determineRedirect(WP_User $user): string
    {
        // 1. Default fallback: Home page
        $redirect_to = home_url('/');

        // 2. Default WP behavior: Admins to dashboard
        if (user_can($user, 'manage_options')) {
            $redirect_to = admin_url();
        }

        /**
         * Filter the Gateway redirect URL.
         * 
         * This allows Launchpad (or any other module) to hijack 
         * the redirect without the Gateway knowing it exists.
         * 
         * @param string  $redirect_to The proposed URL.
         * @param WP_User $user        The authenticated user object.
         */
        return apply_filters('gateway_auth_redirect_url', $redirect_to, $user);

        /*// Check if Launchpad is active and user should use it
        if (function_exists('launchpad')) {
            try {
                $launchpad = \launchpad();
                // Use reflection or method check for safety
                $accessController = $launchpad->registry(); // Placeholder

                // Check for AccessController method
                if (
                    method_exists($launchpad, 'accessController') &&
                    $launchpad->accessController() instanceof \Launchpad\Core\AccessController
                ) {
                    $access = $launchpad->accessController();
                    if (
                        method_exists($access, 'shouldUseLaunchpad') &&
                        $access->shouldUseLaunchpad($user->ID)
                    ) {
                        return home_url('/launchpad/');
                    }
                }
            } catch (\Throwable $e) {
                // Launchpad not properly initialized, fall through
            }
        }

        // Admins go to wp-admin
        if (user_can($user, 'manage_options')) {
            return admin_url();
        }
        // Default: home page
        return home_url('/'); */
    }

    /**
     * Check if user/IP combination is rate limited.
     */
    private function isRateLimited(string $username): bool
    {
        $key = $this->getRateLimitKey($username);
        $attempts = (int) get_transient($key);

        return $attempts >= self::MAX_ATTEMPTS;
    }

    /**
     * Record a failed login attempt.
     */
    private function recordFailedAttempt(string $username): void
    {
        $key = $this->getRateLimitKey($username);
        $attempts = (int) get_transient($key);

        set_transient($key, $attempts + 1, self::LOCKOUT_DURATION);
    }

    /**
     * Clear rate limiting on successful login.
     */
    private function clearAttempts(string $username): void
    {
        delete_transient($this->getRateLimitKey($username));
    }

    /**
     * Generate rate limit key from username and IP.
     */
    private function getRateLimitKey(string $username): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return 'gw_login_' . md5($username . $ip);
    }
}
