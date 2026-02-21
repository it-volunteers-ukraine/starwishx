<?php

/**
 * Service handling the two-step password recovery flow.
 * Matches WP Core: lostpassword (request) and rp (reset).
 * 
 * File: inc/gateway/Services/PasswordResetService.php
 */

declare(strict_types=1);

namespace Gateway\Services;

use Shared\Policy\PasswordPolicy;
use WP_Error;
use WP_User;

class PasswordResetService
{
    private const RATE_LIMIT_MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_TIMEOUT_MINUTES = 15;

    /**
     * Phase 1: Handle the "Lost Password" request.
     * Generates a key and sends the email link.
     *
     * @param string $user_login Username or Email.
     * @return true|WP_Error
     */
    public function handleLostPassword(string $user_login): bool|WP_Error
    {
        if (empty($user_login)) {
            return new WP_Error('empty_username', __('Please enter a username or email.', 'starwishx'));
        }

        // CRITICAL: Check rate limit BEFORE any user lookup
        // This prevents enumeration via timing attacks
        $rate_check = $this->checkRateLimit($user_login);
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        // Identify user by login or email
        if (strpos($user_login, '@')) {
            $user = get_user_by('email', $user_login);
            if (!$user) {
                $user = get_user_by('login', $user_login);
            }
        } else {
            $user = get_user_by('login', $user_login);
        }

        // Security: If user doesn't exist, return true so we don't leak account existence.
        if (!$user) {
            return true;
        }

        // Generate the WP activation key
        $key = get_password_reset_key($user);
        if (is_wp_error($key)) {
            return $key;
        }

        // Build the "rp" (Reset Password) URL for our Gateway app
        $reset_url = add_query_arg([
            'view'  => 'reset-password',
            'key'   => $key,
            'login' => rawurlencode($user->user_login),
        ], home_url('/gateway/'));

        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        $message = __('Someone has requested a password reset for the following account:', 'starwishx') . "\r\n\r\n";
        $message .= sprintf(__('Site Name: %s', 'starwishx'), $site_name) . "\r\n\r\n";
        $message .= sprintf(__('Username: %s', 'starwishx'), $user->user_login) . "\r\n\r\n";
        $message .= __('If this was a mistake, ignore this email and nothing will happen.', 'starwishx') . "\r\n\r\n";
        $message .= __('To reset your password, visit the following address:', 'starwishx') . "\r\n\r\n";
        $message .= $reset_url . "\r\n";

        $subject = sprintf(__('[%s] Password Reset', 'starwishx'), $site_name);

        $sent = wp_mail($user->user_email, $subject, $message);

        if (!$sent) {
            // SECURITY: Log failure but return true to prevent user enumeration
            error_log(sprintf(
                '[Gateway] Password reset email failed for user: %s (IP: %s)',
                $user->user_login,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ));
            // Still return true - don't leak user existence via email failures
            return true;
        }

        return true;
    }

    /**
     * Validation: Check if the key/login combo is still valid.
     * Used to protect the Reset Password form.
     */
    public function validateKey(string $key, string $login): WP_User|WP_Error
    {
        $user = check_password_reset_key($key, $login);

        if (is_wp_error($user)) {
            return new WP_Error('invalid_key', __('This link is invalid or expired. Please request a new one.', 'starwishx'));
        }

        return $user;
    }

    /**
     * Phase 2: The "rp" action. 
     * Validates the key one last time and updates the password.
     */
    public function resetPassword(string $key, string $login, string $new_password): bool|WP_Error
    {
        $user = $this->validateKey($key, $login);
        if (is_wp_error($user)) {
            return $user;
        }

        // Use enhanced password validation
        $validation = $this->validatePasswordStrength($new_password, $user);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Uses the function from your provided user.php (Line 2118)
        reset_password($user, $new_password);

        return true;
    }

    /**
     * Validate password strength using WordPress standards.
     *
     * @param string $password Password to validate
     * @param WP_User $user User object for checking against user data
     * @return true|WP_Error
     */
    private function validatePasswordStrength(string $password, WP_User $user): bool|WP_Error
    {
        // Check minimum length (WordPress recommends 12 as of 2024+)
        if (strlen($password) < PasswordPolicy::MIN_LENGTH) {
            return new WP_Error(
                'password_too_short',
                sprintf(
                    __('Password must be at least %d characters long.', 'starwishx'),
                    PasswordPolicy::MIN_LENGTH
                )
            );
        }

        // Check for required character types
        $has_uppercase = preg_match('/[A-Z]/', $password);
        $has_number = preg_match('/[0-9]/', $password);
        $has_special = preg_match('/[^A-Za-z0-9]/', $password); // Special characters

        if (!$has_uppercase || !$has_number || !$has_special) {
            return new WP_Error(
                'password_too_weak',
                __('Please use a mix of uppercase letters, numbers, and symbols.', 'starwishx')
            );
        }

        // Prevent password from containing user data
        $check_data = array_filter([
            $user->user_login,
            $user->user_email,
            $user->display_name,
        ]);

        foreach ($check_data as $check) {
            // Case-insensitive check for user data in password
            if (stripos($password, $check) !== false) {
                return new WP_Error(
                    'password_contains_userdata',
                    __('Password cannot contain your username, email, or name.', 'starwishx')
                );
            }
        }

        return true;
    }
    /**
     * Check rate limiting for password reset requests.
     * Uses combination of user_login + IP to prevent global user lockout.
     *
     * @param string $userLogin Username or email attempting reset
     * @return true|WP_Error
     */
    private function checkRateLimit(string $userLogin): bool|WP_Error
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Combine user login and IP for the transient key
        // This prevents attackers from locking out a user globally
        $identifier = sanitize_key($userLogin . '_' . $ip);
        $transient_key = "pwd_reset_attempts_{$identifier}";

        $attempts = (int) get_transient($transient_key);

        if ($attempts >= self::RATE_LIMIT_MAX_ATTEMPTS) {
            // Log the blocked attempt
            error_log(sprintf(
                '[Gateway Security] Password reset rate limit exceeded for user: %s from IP: %s',
                $userLogin,
                $ip
            ));

            return new WP_Error(
                'too_many_attempts',
                sprintf(
                    __('Too many password reset attempts. Please try again in %d minutes.', 'starwishx'),
                    self::RATE_LIMIT_TIMEOUT_MINUTES
                )
            );
        }

        // Increment attempt counter
        set_transient(
            $transient_key,
            $attempts + 1,
            self::RATE_LIMIT_TIMEOUT_MINUTES * MINUTE_IN_SECONDS
        );

        return true;
    }

    /**
     * Generate a strong password.
     * 
     * @return string
     */
    public function generatePassword(): string
    {
        // 24 characters, with standard and extra special characters
        return wp_generate_password(24, true, true);
    }
}
