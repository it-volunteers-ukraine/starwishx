<?php

/**
 * Multicast dispatcher for editor-broadcast events.
 *
 * Mirrors the shape of Contact\Channels\ChannelDispatcher: per-channel
 * enabled/configured guards, "any-success" semantics, diagnostic return
 * shape for callers. The retryable flag tells the caller whether a transient
 * failure occurred (network glitch / 5xx) versus a permanent failure
 * (4xx config error, unknown payload type) so the caller can decide whether
 * to schedule a retry.
 *
 * File: inc/notifications/Broadcast/EditorBroadcaster.php
 */

declare(strict_types=1);

namespace Notifications\Broadcast;

use Shared\Log\Logger;
use WP_Error;

final class EditorBroadcaster
{
    /** @var BroadcastChannelInterface[] */
    private array $channels;

    /**
     * @param BroadcastChannelInterface[]|null $channels Override the default channel set (tests).
     */
    public function __construct(?array $channels = null)
    {
        $this->channels = $channels ?? [
            new TelegramEditorChannel(),
        ];
    }

    /**
     * Dispatch a payload to all enabled+configured broadcast channels.
     *
     * @return array{attempted:int, succeeded:int, failures:string[], retryable:bool}
     */
    public function dispatch(BroadcastPayloadInterface $payload): array
    {
        $attempted = 0;
        $succeeded = 0;
        $failures  = [];
        $retryable = false;

        foreach ($this->channels as $channel) {
            if (! $channel->isEnabled()) {
                continue;
            }

            if (! $channel->isConfigured()) {
                Logger::warning('Notifications', 'Broadcast channel skipped: not configured', [
                    'channel' => $channel->getId(),
                ]);
                continue;
            }

            $attempted++;

            $result = $channel->send($payload);

            if ($result === true) {
                $succeeded++;
                continue;
            }

            $failures[] = $channel->getId();
            if ($result instanceof WP_Error && self::isRetryable($result)) {
                $retryable = true;
            }
        }

        return [
            'attempted' => $attempted,
            'succeeded' => $succeeded,
            'failures'  => $failures,
            'retryable' => $retryable,
        ];
    }

    /**
     * Classify a channel failure as transient (worth retrying) or permanent.
     */
    private static function isRetryable(WP_Error $error): bool
    {
        $code = $error->get_error_code();

        if ($code === 'telegram_network') {
            return true;
        }

        if ($code === 'telegram_api') {
            $data   = $error->get_error_data();
            $status = (int) ($data['httpStatus'] ?? 0);
            return $status >= 500 && $status < 600;
        }

        return false;
    }
}
