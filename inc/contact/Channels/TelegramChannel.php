<?php

/**
 * Telegram delivery channel for contact form submissions.
 *
 * Reads admin credentials from the theme options page:
 *   - tg_bot_token: Telegram Bot API token
 *   - tg_chat_id:   numeric chat ID, or @channelname
 *
 * Transport is delegated to Shared\Telegram\TelegramClient; this class owns
 * only the contact-specific composition (HTML body shape, label strings).
 *
 * File: inc/contact/Channels/TelegramChannel.php
 */

declare(strict_types=1);

namespace Contact\Channels;

use Contact\Dto\ContactMessage;
use Shared\Policy\TelegramConfigPolicy;
use Shared\Telegram\TelegramClient;
use WP_Error;

final class TelegramChannel implements ChannelInterface
{
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
        $client = new TelegramClient(
            $this->readToken(),
            $this->readChatId(),
            'Contact'
        );

        return $client->sendHtml($this->composeText($message));
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
            TelegramClient::escape(__('New message from website', 'starwishx')),
            TelegramClient::escape(__('Name', 'starwishx')),
            TelegramClient::escape($message->name),
            TelegramClient::escape(__('Phone', 'starwishx')),
            TelegramClient::escape($phone),
            TelegramClient::escape(__('Email', 'starwishx')),
            TelegramClient::escape($message->email),
            TelegramClient::escape(__('Message', 'starwishx')),
            TelegramClient::escape($message->message)
        );
    }
}
