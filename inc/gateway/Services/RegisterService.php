<?php

/**
 * Service handling user registration with email activation.
 * Aligns with WordPress native behavior: register with email, then set password via link.
 * 
 * File: inc/gateway/Services/RegisterService.php
 */

declare(strict_types=1);

namespace Gateway\Services;

use WP_Error;
use WP_User;

/**
 * Service handling user registration via email activation.
 */
class RegisterService
{
    public function handleRegistration(string $username, string $email): bool|WP_Error
    {
        // 1. Basic Validation
        if (empty($username) || !is_email($email)) {
            return new WP_Error('invalid_data', __('Please provide a valid username and email address.', 'starwishx'));
        }

        // 2. Existence Checks (Standard WP behavior allows these leaks on registration for UX)
        if (username_exists($username)) {
            return new WP_Error('username_exists', __('This username is already taken.', 'starwishx'));
        }
        if (email_exists($email)) {
            return new WP_Error('email_exists', __('This email is already registered.', 'starwishx'));
        }

        // 3. Create User with Random Password
        $user_id = wp_insert_user([
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password(24, true, true),
            'role'         => get_option('default_role', 'subscriber'),
        ]);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        $user = get_user_by('id', $user_id);

        // 4. Trigger standard WP registration hook for plugin compatibility
        do_action('register_new_user', $user_id);

        // 5. Send Activation Email
        return $this->sendActivationEmail($user);
    }

    private function sendActivationEmail(WP_User $user): bool|WP_Error
    {
        $key = get_password_reset_key($user);
        if (is_wp_error($key)) {
            return $key;
        }

        // Reuse the Reset Password view
        $activation_url = add_query_arg([
            'view'  => 'reset-password',
            'key'   => $key,
            'login' => rawurlencode($user->user_login),
        ], home_url('/gateway/'));

        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $subject   = sprintf(__('[%s] Activate Your Account', 'starwishx'), $site_name);

        $message  = sprintf(__('Welcome, %s!', 'starwishx'), $user->user_login) . "\r\n\r\n";
        $message .= __('To complete your registration and set your password, click the link below:', 'starwishx') . "\r\n";
        $message .= $activation_url . "\r\n\r\n";
        $message .= __('If you did not request this, please ignore this email.', 'starwishx');

        $sent = wp_mail($user->user_email, $subject, $message);

        if (!$sent) {
            // Log but return true to prevent UI breakage/enumeration
            error_log("[Gateway] Email failed for registered user: {$user->user_login}");
            return true;
        }

        return true;
    }
}
