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
use Shared\Http\ClientIp;
use Shared\Policy\EmailPolicy;
use Shared\Sanitize\InputSanitizer;
use Shared\Policy\RateLimitPolicy;
use Contact\Channels\ChannelDispatcher;
use Contact\Core\ContactCore;
use Contact\Dto\ContactMessage;
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
            'permission_callback' => [$this, 'checkRestNonce'],
        ]);
    }

    /**
     * Handle contact form submission.
     */
    public function send(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        /* ---- Rate limit: IP-based, every attempt counts ---- */
        $rl_key = RateLimitPolicy::key('contact', ClientIp::resolve());

        $rl_message = sprintf(
            /* translators: 1: action name, 2: human-readable wait duration */
            __('%1$s limit reached. Please wait %2$s before trying again.', 'starwishx'),
            __('Contact form', 'starwishx'),
            human_time_diff(time(), time() + self::RATE_LIMIT_WINDOW)
        );

        $rl_check = RateLimitPolicy::check(
            $rl_key,
            self::RATE_LIMIT_MAX,
            self::RATE_LIMIT_WINDOW,
            $rl_message
        );
        if (is_wp_error($rl_check)) {
            return $this->mapServiceError($rl_check);
        }
        RateLimitPolicy::hit($rl_key, self::RATE_LIMIT_WINDOW);

        /* ---- Honeypot: silent success to avoid tipping off bots ---- */
        if (! empty($request->get_param('honeypot'))) {
            return $this->success([
                'message' => __('Message sent successfully!', 'starwishx'),
            ]);
        }

        /* ---- Raw input, capped at field limits (log/memory hardening) ---- */
        $raw_name    = mb_substr((string) ($request->get_param('name')    ?? ''), 0, ContactCore::NAME_MAX_LENGTH,    'UTF-8');
        $raw_email   = mb_substr((string) ($request->get_param('email')   ?? ''), 0, ContactCore::EMAIL_MAX_LENGTH,   'UTF-8');
        $raw_phone   = mb_substr((string) ($request->get_param('phone')   ?? ''), 0, ContactCore::PHONE_MAX_LENGTH,   'UTF-8');
        $raw_message = mb_substr((string) ($request->get_param('message') ?? ''), 0, ContactCore::MESSAGE_MAX_LENGTH, 'UTF-8');

        /* ---- Email: RFC 5321/5322 validation ---- */
        if (empty($raw_email)) {
            return $this->error(
                __('Please enter a valid email address', 'starwishx'),
                422,
                'invalid_email'
            );
        }

        $emailResult = EmailPolicy::validate($raw_email);
        if (is_wp_error($emailResult)) {
            return $this->mapServiceError($emailResult);
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
        $name    = InputSanitizer::sanitizeText($raw_name);
        $email   = sanitize_email($raw_email);
        $message = InputSanitizer::sanitizeTextarea($raw_message);

        // sanitize_email can strip a value that passed EmailPolicy to empty
        // (quirky but policy-valid locals). Re-check before we compose a mail
        // header with it.
        if (empty($email)) {
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

        /* ---- Dispatch across enabled channels ---- */
        $contactMessage = new ContactMessage(
            name:    $name,
            email:   $email,
            phone:   $phone,
            message: $message,
        );

        $result = (new ChannelDispatcher())->dispatch($contactMessage);

        if ($result['attempted'] === 0) {
            return $this->error(
                __('Server error. Please try again later.', 'starwishx'),
                500,
                'no_channel'
            );
        }

        if ($result['succeeded'] === 0) {
            return $this->error(
                __('Server error. Please try again later.', 'starwishx'),
                500,
                'delivery_failed'
            );
        }

        return $this->success([
            'message' => __('Message sent successfully!', 'starwishx'),
        ]);
    }
}
