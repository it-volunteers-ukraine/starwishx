<?php

/**
 * Service handling the two-step password recovery flow.
 * Matches WP Core: lostpassword (request) and rp (reset).
 *
 * File: inc/gateway/Services/PasswordResetService.php
 */

declare(strict_types=1);

namespace Gateway\Services;

use Gateway\Enums\GatewayView;
use Shared\Http\ClientIp;
use Shared\Policy\PasswordPolicy;
use WP_Error;
use WP_User;

final class PasswordResetService
{
    /**
     * Phase 1: Handle the "Lost Password" request.
     * Generates a key and sends the email link.
     *
     * The answer is the same whether or not the account exists. The time
     * isn't (an existing account costs a key and an email), but /register
     * deliberately says whether a login or email is taken, so hiding it
     * here alone would buy nothing; the controller's rate limits apply to
     * both.
     *
     * @param string $user_login Username or Email.
     * @return true|WP_Error
     */
    #[\NoDiscard]
    public function handleLostPassword(string $user_login): bool|WP_Error
    {
        if ($user_login === '') {
            return new WP_Error('empty_username', __('Please enter a username or email.', 'starwishx'));
        }

        // Identify user by email (when it looks like one) or login
        $user = (str_contains($user_login, '@') ? get_user_by('email', $user_login) : false)
            ?: get_user_by('login', $user_login);

        // Security: If user doesn't exist, return true so we don't leak account existence.
        if (!$user) {
            return true;
        }

        // Generate the WP activation key
        $key = get_password_reset_key($user);
        if (is_wp_error($key)) {
            return $key;
        }

        $reset_url = GatewayView::resetUrl($key, $user->user_login);

        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        $message = __('Someone has requested a password reset for the following account:', 'starwishx') . "\r\n\r\n";
        $message .= sprintf(__('Site Name: %s', 'starwishx'), $site_name) . "\r\n\r\n";
        $message .= sprintf(__('Username: %s', 'starwishx'), $user->user_login) . "\r\n\r\n";
        $message .= __('If this was a mistake, ignore this email and nothing will happen.', 'starwishx') . "\r\n\r\n";
        $message .= __('To reset your password, visit the following address:', 'starwishx') . "\r\n\r\n";
        $message .= $reset_url . "\r\n";

        $subject = sprintf(__('[%s] Password Reset', 'starwishx'), $site_name);

        if (!wp_mail($user->user_email, $subject, $message)) {
            // Log, but answer as for any other request.
            error_log(sprintf(
                '[Gateway] Password reset email failed for user: %s (IP: %s)',
                $user->user_login,
                ClientIp::resolve()
            ));
        }

        return true;
    }

    /**
     * Validation: Check if the key/login combo is still valid.
     * Used to protect the Reset Password form.
     */
    #[\NoDiscard]
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
    #[\NoDiscard]
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

        // Core's reset: sets the password (which also clears the reset key)
        // and fires password_reset / after_password_reset.
        reset_password($user, $new_password);

        return true;
    }

    /**
     * Generate a strong password: 24 characters, with standard and extra
     * special characters.
     */
    public function generatePassword(): string
    {
        return wp_generate_password(24, true, true);
    }
}
