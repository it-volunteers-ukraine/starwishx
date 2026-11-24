<?php
//File: inc/gateway/Services/AuthService.php

declare(strict_types=1);

namespace Gateway\Services;

use Shared\Http\ClientIp;
use Shared\Policy\RateLimitPolicy;
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
final class AuthService
{
    /** Failed logins allowed per account and IP... */
    private const int ACCOUNT_MAX_FAILURES = 5;

    /** ...and per IP over all accounts (password spraying from one IP). */
    private const int IP_MAX_FAILURES = 20;

    private const int LOCKOUT_WINDOW = 15 * MINUTE_IN_SECONDS;

    /**
     * Authenticate user with rate limiting protection.
     *
     * Only failures count. Success clears the account's counter but not the
     * IP's - logging into one's own account must not reset a spray.
     *
     * @param string $username Username or email
     * @param string $password Plain text password
     * @param bool $remember Create persistent cookie
     * @return array{user: WP_User, redirect_to: string}|WP_Error
     */
    #[\NoDiscard]
    public function authenticate(
        string $username,
        // PHP 8.5 prints backtraces for fatal errors: never with this in them.
        #[\SensitiveParameter] string $password,
        bool $remember = false
    ): array|WP_Error {
        $keys = $this->rateLimitKeys($username);
        $message = __('Too many login attempts. Please wait 15 minutes.', 'starwishx');

        foreach ([[$keys['account'], self::ACCOUNT_MAX_FAILURES], [$keys['ip'], self::IP_MAX_FAILURES]] as [$key, $max]) {
            $check = RateLimitPolicy::check($key, $max, self::LOCKOUT_WINDOW, $message);
            if (is_wp_error($check)) {
                return $check; // rate_limited
            }
        }

        // Authenticate via WordPress
        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            RateLimitPolicy::hit($keys['account'], self::LOCKOUT_WINDOW);
            RateLimitPolicy::hit($keys['ip'], self::LOCKOUT_WINDOW);

            // Return generic error to prevent user enumeration
            return new WP_Error(
                'auth_failed',
                __('Invalid username or password.', 'starwishx')
            );
        }

        // Activation gate — block login for accounts that haven't completed
        // the activation flow (clicking the emailed link + setting password).
        // Must fire before wp_set_auth_cookie so inactive bots never get a session.
        if (function_exists('users') && !\users()->stateService()->isActivated($user->ID)) {
            return new WP_Error(
                'account_not_activated',
                __('Please activate your account via the link sent to your email.', 'starwishx')
            );
        }

        RateLimitPolicy::clear($keys['account']);

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
     * Strictly agnostic: basic WP roles here, specialized logic (the
     * Launchpad) through the filter.
     */
    private function determineRedirect(WP_User $user): string
    {
        $redirect_to = user_can($user, 'manage_options') ? admin_url() : home_url('/');

        /**
         * Filter the Gateway redirect URL.
         *
         * This allows Launchpad (or any other module) to hijack
         * the redirect without the Gateway knowing it exists.
         *
         * @param string  $redirect_to The proposed URL.
         * @param WP_User $user        The authenticated user object.
         */
        return (string) apply_filters('gateway_auth_redirect_url', $redirect_to, $user);
    }

    /**
     * The failure counters for an attempt: one for the account from this IP,
     * one for the IP. WordPress takes a login in any letter case, or the
     * email, so the account is keyed by the user it names - every spelling
     * of an account shares one counter - and a name no account has by its
     * lower-cased self.
     *
     * @return array{account: string, ip: string}
     */
    private function rateLimitKeys(string $username): array
    {
        $name = $username |> trim(...) |> strtolower(...);
        $user = get_user_by('login', $name)
            ?: (str_contains($name, '@') ? get_user_by('email', $name) : false);
        $ip = ClientIp::resolve();

        return [
            'account' => RateLimitPolicy::key('gateway.login', $user ? "user:{$user->ID}" : "name:{$name}", $ip),
            'ip'      => RateLimitPolicy::key('gateway.login_ip', $ip),
        ];
    }
}
