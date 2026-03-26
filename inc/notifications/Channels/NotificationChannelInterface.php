<?php
// File: inc/notifications/Channels/NotificationChannelInterface.php

declare(strict_types=1);

namespace Notifications\Channels;

interface NotificationChannelInterface
{
    /**
     * Whether this channel handles the given channel type string.
     */
    public function supports(string $channelType): bool;

    /**
     * Send a notification. Returns true on success, false on failure.
     *
     * @param object $notification Row from sw_notifications table.
     */
    public function send(object $notification): bool;
}
