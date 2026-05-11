<?php

/**
 * Contact channel dispatcher.
 *
 * Multicasts a ContactMessage across every enabled+configured channel.
 * Semantics: "any-success" — the visitor sees success if at least one
 * channel delivered. Per-channel transport detail is logged inside each
 * channel; the dispatcher logs orchestration outcomes that channels
 * can't see (skipped configs, total failure, no channels available).
 *
 * File: inc/contact/Channels/ChannelDispatcher.php
 */

declare(strict_types=1);

namespace Contact\Channels;

use Contact\Core\ContactCore;
use Contact\Dto\ContactMessage;
use Shared\Log\Logger;

final class ChannelDispatcher
{
    /** @var ChannelInterface[] */
    private array $channels;

    /**
     * @param ChannelInterface[]|null $channels Override the default channel set (tests).
     */
    public function __construct(?array $channels = null)
    {
        $this->channels = $channels ?? [
            new EmailChannel(),
            new TelegramChannel(),
        ];
    }

    /**
     * Send the message across all enabled+configured channels.
     *
     * @return array{attempted:int, succeeded:int, failures:string[]}
     */
    public function dispatch(ContactMessage $message): array
    {
        $attempted   = 0;
        $succeeded   = 0;
        $failures    = [];
        $diagnostics = [];

        foreach ($this->channels as $channel) {
            $enabled    = $channel->isEnabled();
            $configured = $enabled && $channel->isConfigured();

            $diagnostics[] = [
                'id'         => $channel->getId(),
                'enabled'    => $enabled,
                'configured' => $configured,
            ];

            if (! $enabled) {
                continue;
            }

            if (! $configured) {
                Logger::warning('Contact', 'Channel skipped: not configured', [
                    'channel' => $channel->getId(),
                ]);
                continue;
            }

            $attempted++;

            $result = $channel->send($message);

            if ($result === true) {
                $succeeded++;
                if (ContactCore::LOG_SUCCESSFUL_DELIVERY) {
                    Logger::debug('Contact', 'Channel delivered', [
                        'channel' => $channel->getId(),
                    ]);
                }
            } else {
                // The channel already logged the transport detail; aggregate the id here.
                $failures[] = $channel->getId();
            }
        }

        if ($attempted === 0) {
            Logger::error('Contact', 'No channels available for dispatch', [
                'channels' => $diagnostics,
            ]);
        } elseif ($succeeded === 0) {
            Logger::warning('Contact', 'All channels failed', [
                'attempted' => $attempted,
                'failures'  => $failures,
            ]);
        }

        return [
            'attempted' => $attempted,
            'succeeded' => $succeeded,
            'failures'  => $failures,
        ];
    }
}
