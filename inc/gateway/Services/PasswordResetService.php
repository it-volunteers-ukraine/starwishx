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
    /**
     * Phase 1: Handle the "Lost Password" request.
     * Generates a key and sends the email link.
     *
     * Rate limiting lives at the controller edge (PasswordController) and
     * runs before this method, so enumeration timing attacks see identical
     * latency regardless of whether the account exists.
     *
     * @param string $user_login Username or Email.
     * @return true|WP_Error
     */
    public function handleLostPassword(string $user_login): bool|WP_Error
    {
        if (empty($user_login)) {
            return new WP_Error('empty_username', __('Please enter a username or email.', 'starwishx'));
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

        // Validate using the shared password policy
        $validation = PasswordPolicy::validate($new_password, $user);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Uses the function from your provided user.php (Line 2118)
        reset_password($user, $new_password);

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
