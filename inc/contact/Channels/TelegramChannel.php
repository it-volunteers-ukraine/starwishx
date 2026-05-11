<?php

/**
 * Telegram delivery channel for contact form submissions.
 *
 * Reads admin credentials from the theme options page:
 *   - tg_bot_token: Telegram Bot API token
 *   - tg_chat_id:   numeric chat ID, or @channelname
 *
 * Composition uses Telegram's HTML parse_mode. Only &, <, > are escaped
 * per the Bot API spec — no other HTML entities are required or accepted.
 *
 * File: inc/contact/Channels/TelegramChannel.php
 */

declare(strict_types=1);

namespace Contact\Channels;

use Contact\Dto\ContactMessage;
use Shared\Log\Logger;
use Shared\Policy\TelegramConfigPolicy;
use WP_Error;

final class TelegramChannel implements ChannelInterface
{
    private const API_BASE = 'https://api.telegram.org/bot';
    private const TIMEOUT  = 8;

    public function getId(): string
    {
        return 'telegram';
    }

    public function isEnabled(): bool
    {
        return (bool) get_field('use_telegram_channel', 'option');
    }

    public function isConfigured(): bool
    {
        return ! is_wp_error(
            TelegramConfigPolicy::validate($this->readToken(), $this->readChatId())
        );
    }

    public function send(ContactMessage $message): bool|WP_Error
    {
        $token  = $this->readToken();
        $chatId = $this->readChatId();

        $response = wp_remote_post(
            self::API_BASE . $token . '/sendMessage',
            [
                'timeout' => self::TIMEOUT,
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode([
                    'chat_id'    => $chatId,
                    'text'       => $this->composeText($message),
                    'parse_mode' => 'HTML',
                ]),
            ]
        );

        if (is_wp_error($response)) {
            Logger::error('Contact', 'Telegram transport failure', [
                'channel' => $this->getId(),
                'error'   => $response->get_error_message(),
            ]);
            return new WP_Error(
                'telegram_network',
                __('Telegram transport error.', 'starwishx')
            );
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            Logger::error('Contact', 'Telegram API error', [
                'channel'    => $this->getId(),
                'httpStatus' => $status,
                'response'   => wp_remote_retrieve_body($response),
            ]);
            return new WP_Error(
                'telegram_api',
                __('Telegram rejected the message.', 'starwishx')
            );
        }

        return true;
    }

    private function readToken(): string
    {
        return (string) (get_field('tg_bot_token', 'option') ?: '');
    }

    private function readChatId(): string
    {
        return (string) (get_field('tg_chat_id', 'option') ?: '');
    }

    /**
     * Build the message body in Telegram HTML. Visitor-supplied values are
     * escaped because parse_mode=HTML treats &, <, > as control characters.
     */
    private function composeText(ContactMessage $message): string
    {
        $phone = $message->phone !== ''
            ? $message->phone
            : __('Not specified', 'starwishx');

        return sprintf(
            "<b>%s</b>\n\n<b>%s:</b> %s\n<b>%s:</b> %s\n<b>%s:</b> %s\n\n<b>%s:</b>\n%s",
            $this->escape(__('New message from website', 'starwishx')),
            $this->escape(__('Name', 'starwishx')),
            $this->escape($message->name),
            $this->escape(__('Phone', 'starwishx')),
            $this->escape($phone),
            $this->escape(__('Email', 'starwishx')),
            $this->escape($message->email),
            $this->escape(__('Message', 'starwishx')),
            $this->escape($message->message)
        );
    }

    private function escape(string $value): string
    {
        return strtr($value, [
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
        ]);
    }
}
