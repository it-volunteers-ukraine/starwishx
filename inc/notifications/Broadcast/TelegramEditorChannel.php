<?php

/**
 * Telegram delivery for editor-broadcast events.
 *
 * Reads credentials from the theme options page (tg_bot_token, tg_chat_id),
 * gated by the use_telegram_channel_for_editor_notifications toggle from the
 * same options group. Composition uses Telegram's HTML parse_mode.
 *
 * Adding a new event type means adding a compose branch in `compose()` and a
 * private composer method. Channel itself stays transport-only; HTTP lives
 * in Shared\Telegram\TelegramClient.
 *
 * File: inc/notifications/Broadcast/TelegramEditorChannel.php
 */

declare(strict_types=1);

namespace Notifications\Broadcast;

use Notifications\Broadcast\Payloads\OpportunityPendingPayload;
use Shared\Log\Logger;
use Shared\Policy\TelegramConfigPolicy;
use Shared\Telegram\TelegramClient;
use WP_Error;

final class TelegramEditorChannel implements BroadcastChannelInterface
{
    public function getId(): string
    {
        return 'telegram_editor';
    }

    public function isEnabled(): bool
    {
        return (bool) get_field('use_telegram_channel_for_editor_notifications', 'option');
    }

    public function isConfigured(): bool
    {
        return ! is_wp_error(
            TelegramConfigPolicy::validate($this->readToken(), $this->readChatId())
        );
    }

    public function send(BroadcastPayloadInterface $payload): bool|WP_Error
    {
        $html = $this->compose($payload);
        if ($html === null) {
            Logger::warning('Notifications', 'Unsupported broadcast payload', [
                'channel'   => $this->getId(),
                'eventType' => $payload->getEventType(),
            ]);
            return new WP_Error(
                'broadcast_unknown_type',
                __('Unsupported broadcast payload type.', 'starwishx')
            );
        }

        $client = new TelegramClient($this->readToken(), $this->readChatId(), 'Notifications');
        return $client->sendHtml($html);
    }

    private function compose(BroadcastPayloadInterface $payload): ?string
    {
        return match (true) {
            $payload instanceof OpportunityPendingPayload => $this->composeOpportunityPending($payload),
            default => null,
        };
    }

    private function composeOpportunityPending(OpportunityPendingPayload $p): string
    {
        $bodyPlain = sprintf(
            /* translators: 1: actor display name, 2: opportunity title */
            __('%1$s submitted "%2$s" for editorial review.', 'starwishx'),
            $p->actorDisplayName,
            $p->postTitle
        );

        return sprintf(
            "<b>%s</b>\n\n%s\n\n<a href=\"%s\">%s</a> | <a href=\"%s\">%s</a>",
            TelegramClient::escape(__('Opportunity submitted for review', 'starwishx')),
            TelegramClient::escape($bodyPlain),
            TelegramClient::escape($p->previewUrl),
            TelegramClient::escape(__('Preview', 'starwishx')),
            TelegramClient::escape($p->editUrl),
            TelegramClient::escape(__('Edit in admin', 'starwishx'))
        );
    }

    private function readToken(): string
    {
        return (string) (get_field('tg_bot_token', 'option') ?: '');
    }

    private function readChatId(): string
    {
        return (string) (get_field('tg_chat_id', 'option') ?: '');
    }
}
