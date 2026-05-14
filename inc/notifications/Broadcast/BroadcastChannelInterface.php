<?php

/**
 * Broadcast delivery channel contract.
 *
 * Same shape as Contact\Channels\ChannelInterface but in its own namespace
 * so the two contracts evolve independently — broadcast channels deal with
 * group destinations (TG group, Slack channel, …) rather than per-user
 * recipients, and may grow channel-specific concerns the contact contract
 * shouldn't carry.
 *
 * File: inc/notifications/Broadcast/BroadcastChannelInterface.php
 */

declare(strict_types=1);

namespace Notifications\Broadcast;

use WP_Error;

interface BroadcastChannelInterface
{
    /** Stable identifier (e.g. "telegram_editor") used in logs and diagnostics. */
    public function getId(): string;

    /** Whether the admin has toggled this channel on via ACF options. */
    public function isEnabled(): bool;

    /** Whether the admin's credentials/destination for this channel are valid. */
    public function isConfigured(): bool;

    /**
     * Deliver the payload.
     *
     * Error data should carry 'httpStatus' on API failures so callers can
     * classify retryable vs permanent (5xx vs 4xx).
     *
     * @return true|WP_Error
     */
    public function send(BroadcastPayloadInterface $payload): bool|WP_Error;
}
