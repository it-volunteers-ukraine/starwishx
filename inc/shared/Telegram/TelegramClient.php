<?php

/**
 * Shared\Telegram\TelegramClient — minimal HTTP client for the Telegram Bot API.
 *
 * Stateless aside from the credentials passed in at construction. Callers
 * supply their own log-channel module name so wp-content/uploads/sw/app-*.log
 * lines are attributable to the originating module ("Contact", "Notifications", …).
 *
 * Composition is the caller's job — this client only knows about HTTP transport
 * and parse_mode=HTML semantics. Use ::escape() on user-supplied substrings
 * before sprintf'ing them into HTML.
 *
 * File: inc/shared/Telegram/TelegramClient.php
 */

declare(strict_types=1);

namespace Shared\Telegram;

use Shared\Log\Logger;
use WP_Error;

final class TelegramClient
{
    private const API_BASE = 'https://api.telegram.org/bot';
    private const TIMEOUT  = 8;

    public function __construct(
        private readonly string $token,
        private readonly string $chatId,
        private readonly string $logModule = 'Telegram',
    ) {}

    /**
     * Send an HTML-formatted message to the configured chat.
     *
     * Error data carries 'httpStatus' on API failures so callers that
     * classify retryable vs permanent (5xx vs 4xx) can read it.
     *
     * @return true|WP_Error
     */
    public function sendHtml(string $html): bool|WP_Error
    {
        $response = wp_remote_post(
            self::API_BASE . $this->token . '/sendMessage',
            [
                'timeout' => self::TIMEOUT,
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode([
                    'chat_id'    => $this->chatId,
                    'text'       => $html,
                    'parse_mode' => 'HTML',
                ]),
            ]
        );

        if (is_wp_error($response)) {
            Logger::error($this->logModule, 'Telegram transport failure', [
                'error' => $response->get_error_message(),
            ]);
            return new WP_Error(
                'telegram_network',
                __('Telegram transport error.', 'starwishx')
            );
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            Logger::error($this->logModule, 'Telegram API error', [
                'httpStatus' => $status,
                'response'   => wp_remote_retrieve_body($response),
            ]);
            return new WP_Error(
                'telegram_api',
                __('Telegram rejected the message.', 'starwishx'),
                ['httpStatus' => $status]
            );
        }

        return true;
    }

    /**
     * Escape user-supplied content for parse_mode=HTML.
     * Only &, <, > are reserved by Telegram's HTML parser.
     */
    public static function escape(string $value): string
    {
        return strtr($value, [
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
        ]);
    }
}
