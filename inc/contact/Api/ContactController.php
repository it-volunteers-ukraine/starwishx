<?php

/**
 * Contact module — REST Controller
 * POST /contact/v1/send
 *
 * File: inc/contact/Api/ContactController.php
 */

declare(strict_types=1);

namespace Contact\Api;

use Shared\Core\AbstractApiController;
use Shared\Sanitize\InputSanitizer;
use Shared\Policy\RateLimitPolicy;
use Contact\Core\ContactCore;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class ContactController extends AbstractApiController
{
    protected $namespace = 'contact/v1';

    private const RATE_LIMIT_MAX      = 3;
    private const RATE_LIMIT_WINDOW   = HOUR_IN_SECONDS;

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/send', [
            'methods'             => 'POST',
            'callback'            => [$this, 'send'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle contact form submission.
     */
    public function send(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        /* ---- Rate limit: IP-based, every submission counts ---- */
        $ip = $request->get_header('X-Forwarded-For')
            ? explode(',', $request->get_header('X-Forwarded-For'))[0]
            : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $rl_key = RateLimitPolicy::key('contact', trim($ip));

        $rl_check = RateLimitPolicy::check(
            $rl_key,
            self::RATE_LIMIT_MAX,
            self::RATE_LIMIT_WINDOW,
            __('Too many messages sent. Please try again later.', 'starwishx')
        );
        if (is_wp_error($rl_check)) {
            return $this->mapServiceError($rl_check);
        }

        /* ---- Honeypot: silent success to avoid tipping off bots ---- */
        if (! empty($request->get_param('honeypot'))) {
            RateLimitPolicy::hit($rl_key, self::RATE_LIMIT_WINDOW);
            return $this->success([
                'message' => __('Message sent successfully!', 'starwishx'),
            ]);
        }

        /* ---- Raw input ---- */
        $raw_name    = (string) ($request->get_param('name')    ?? '');
        $raw_email   = (string) ($request->get_param('email')   ?? '');
        $raw_phone   = (string) ($request->get_param('phone')   ?? '');
        $raw_message = (string) ($request->get_param('message') ?? '');

        /* ---- Email: validate raw, then sanitize, then re-validate ---- */
        if (empty($raw_email) || ! is_email($raw_email)) {
            return $this->error(
                __('Please enter a valid email address', 'starwishx'),
                422,
                'invalid_email'
            );
        }

        $domain = explode('@', $raw_email)[1] ?? '';
        if (strpos($domain, '.') === false) {
            return $this->error(
                __('Please enter a valid email address', 'starwishx'),
                422,
                'invalid_email'
            );
        }

        /* ---- Spam: links in name field ---- */
        if (InputSanitizer::containsUrl($raw_name)) {
            return $this->error(
                __('Links are not allowed in fields', 'starwishx'),
                422,
                'spam_detected'
            );
        }

        /* ---- Sanitize ---- */
        $name  = InputSanitizer::sanitizeText($raw_name);
        $email = sanitize_email($raw_email);

        $char_limit = ContactCore::MESSAGE_MAX_LENGTH;
        if (mb_strlen($raw_message, 'UTF-8') > $char_limit) {
            $raw_message = mb_substr($raw_message, 0, $char_limit, 'UTF-8');
        }
        $message = InputSanitizer::sanitizeTextarea($raw_message);

        /* ---- Email: double-check after sanitize ---- */
        if (empty($email) || ! is_email($email)) {
            return $this->error(
                __('Please enter a valid email address', 'starwishx'),
                422,
                'invalid_email'
            );
        }

        /* ---- Phone: sanitize then validate via PhonePolicy ---- */
        $phone = InputSanitizer::sanitizeText($raw_phone);

        if (! empty($phone)) {
            $validation = \Shared\Policy\PhonePolicy::validate($phone);
            if (is_wp_error($validation)) {
                return $this->mapServiceError($validation);
            }
        }

        /* ---- Required fields ---- */
        if (empty($name) || empty($message)) {
            return $this->error(
                __('Please fill in all required fields', 'starwishx'),
                422,
                'invalid_data'
            );
        }

        /* ---- Compose & send ---- */
        $email_to = get_field('email_link', 'option') ?: get_option('admin_email');
        $subject  = sprintf(
            /* translators: %s: sender name */
            __('Message from website by %s', 'starwishx'),
            $name
        );

        $body = sprintf(
            "%s: %s\n%s: %s\n%s: %s\n\n%s:\n%s",
            __('Name', 'starwishx'),
            $name,
            __('Phone', 'starwishx'),
            $phone ?: __('Not specified', 'starwishx'),
            __('Email', 'starwishx'),
            $email,
            __('Message', 'starwishx'),
            $message
        );

        $headers = [];
        if (! empty($email)) {
            $headers[] = sprintf('Reply-To: %s', $email);
        }

        if (wp_mail($email_to, $subject, $body, $headers)) {
            RateLimitPolicy::hit($rl_key, self::RATE_LIMIT_WINDOW);
            return $this->success([
                'message' => __('Message sent successfully!', 'starwishx'),
            ]);
        }

        /* ---- Mail failure: log and return generic error ---- */
        global $phpmailer;
        if (! empty($phpmailer->ErrorInfo)) {
            error_log('Contact Form SMTP Error: ' . $phpmailer->ErrorInfo);
        }

        return $this->error(
            __('Server error. Please try again later.', 'starwishx'),
            500,
            'mail_error'
        );
    }
}
