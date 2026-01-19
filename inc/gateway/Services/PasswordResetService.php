<?php
// File: inc\gateway\Services\PasswordResetService.php

declare(strict_types=1);

namespace Gateway\Services;

use WP_Error;
use WP_User;

/**
 * Password reset using WordPress native token system.
 */
class PasswordResetService
{
    private const MAX_REQUESTS = 3;
    private const REQUEST_WINDOW = HOUR_IN_SECONDS;

    /**
     * @return true|WP_Error
     */
    public function requestReset(string $email)
    {
        $user = get_user_by('email', $email);

        if (!$user) {
            return true; // Silent success for security
        }

        $key = get_password_reset_key($user);

        if (is_wp_error($key)) {
            return $key;
        }

        $reset_url = add_query_arg([
            'view'  => 'reset-password',
            'key'   => $key,
            'login' => rawurlencode($user->user_login),
        ], home_url('/gateway/'));

        $sent = wp_mail(
            $user->user_email,
            __('Password Reset Request', 'starwishx'),
            sprintf(__('Click here to reset: %s', 'starwishx'), $reset_url)
        );

        return $sent ? true : new WP_Error('email_failed', __('Email failed to send.', 'starwishx'));
    }

    /**
     * @return WP_User|WP_Error
     */
    public function validateToken(string $login, string $key)
    {
        $user = check_password_reset_key($key, $login);

        if (is_wp_error($user)) {
            return new WP_Error('invalid_token', __('Invalid or expired reset link.', 'starwishx'));
        }

        return $user;
    }

    /**
     * @return true|WP_Error
     */
    public function resetPassword(string $login, string $key, string $newPassword)
    {
        $user = $this->validateToken($login, $key);

        if (is_wp_error($user)) {
            return $user;
        }

        if (strlen($newPassword) < 8) {
            return new WP_Error('weak_password', __('Password too short.', 'starwishx'));
        }

        reset_password($user, $newPassword);
        return true;
    }
}
