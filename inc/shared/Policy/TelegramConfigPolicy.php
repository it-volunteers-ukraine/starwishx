<?php

/**
 * Telegram bot credentials validation policy.
 *
 * Validates admin-supplied configuration stored on the theme options page:
 * the Bot API token and the destination chat ID. Mirrors EmailPolicy in
 * shape (static validate() returning true|WP_Error), but is meant for the
 * admin-side credentials path, not user input on every request.
 *
 * File: inc/shared/Policy/TelegramConfigPolicy.php
 */

declare(strict_types=1);

namespace Shared\Policy;

use WP_Error;

final class TelegramConfigPolicy
{
    /** Optional getMe API call to verify the token is live. */
    public const REMOTE_CHECK_ENABLED = false;

    /**
     * Telegram bot tokens: 8–12 digit bot id, colon, then 30+ alphanumeric/_/- chars.
     * Example: 123456789:AAH8m9XnZ-pkqVfXmcRsLY9xPb6T7mEhU1A
     */
    private const TOKEN_PATTERN = '/^\d{8,12}:[A-Za-z0-9_-]{30,}$/';

    /** Numeric chat ID (positive for users, negative for groups/channels). */
    private const CHAT_ID_NUMERIC_PATTERN = '/^-?\d+$/';

    /** @channel handle (5–32 chars, letter-led, letters/digits/underscore). */
    private const CHAT_ID_HANDLE_PATTERN  = '/^@[a-zA-Z][a-zA-Z0-9_]{4,31}$/';

    private const API_BASE = 'https://api.telegram.org/bot';
    private const API_TIMEOUT = 5;

    /**
     * Validate the bot token format.
     *
     * @return true|WP_Error
     */
    public static function validateToken(string $token): bool|WP_Error
    {
        $token = trim($token);
        if ($token === '') {
            return new WP_Error(
                'telegram_token_empty',
                __('Telegram bot token is required.', 'starwishx')
            );
        }

        if (!preg_match(self::TOKEN_PATTERN, $token)) {
            return new WP_Error(
                'telegram_token_invalid',
                __('Telegram bot token format is invalid. Expected: <id>:<token>.', 'starwishx')
            );
        }

        return true;
    }

    /**
     * Validate the chat ID format. Accepts either a numeric ID or a @channel handle.
     *
     * @return true|WP_Error
     */
    public static function validateChatId(string $chatId): bool|WP_Error
    {
        $chatId = trim($chatId);
        if ($chatId === '') {
            return new WP_Error(
                'telegram_chat_id_empty',
                __('Telegram chat ID is required.', 'starwishx')
            );
        }

        if (
            !preg_match(self::CHAT_ID_NUMERIC_PATTERN, $chatId)
            && !preg_match(self::CHAT_ID_HANDLE_PATTERN, $chatId)
        ) {
            return new WP_Error(
                'telegram_chat_id_invalid',
                __('Telegram chat ID must be numeric or a @channel handle.', 'starwishx')
            );
        }

        return true;
    }

    /**
     * Validate both credentials together. Returns the first error encountered.
     *
     * When REMOTE_CHECK_ENABLED is on, also performs a getMe probe against
     * the Telegram API to confirm the token is recognised.
     *
     * @return true|WP_Error
     */
    public static function validate(string $token, string $chatId): bool|WP_Error
    {
        $tokenResult = self::validateToken($token);
        if (is_wp_error($tokenResult)) {
            return $tokenResult;
        }

        $chatResult = self::validateChatId($chatId);
        if (is_wp_error($chatResult)) {
            return $chatResult;
        }

        if (self::REMOTE_CHECK_ENABLED) {
            return self::probeBot(trim($token));
        }

        return true;
    }

    /**
     * Optional live probe — calls Telegram's getMe to verify the token.
     */
    private static function probeBot(string $token): bool|WP_Error
    {
        $response = wp_remote_get(
            self::API_BASE . $token . '/getMe',
            ['timeout' => self::API_TIMEOUT]
        );

        if (is_wp_error($response)) {
            return new WP_Error(
                'telegram_probe_failed',
                __('Could not reach Telegram API to verify the bot.', 'starwishx')
            );
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error(
                'telegram_probe_unauthorized',
                __('Telegram rejected the bot token.', 'starwishx')
            );
        }

        return true;
    }
}
