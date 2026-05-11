<?php

/**
 * Contact delivery channel contract.
 *
 * Each channel ships a fully-formed ContactMessage over a specific transport
 * (email, Telegram, etc.). Channels are independently toggleable; the
 * dispatcher skips channels that are disabled or misconfigured at runtime.
 *
 * Channels own their own composition (subject/body/HTML formatting) so the
 * controller stays transport-agnostic.
 *
 * File: inc/contact/Channels/ChannelInterface.php
 */

declare(strict_types=1);

namespace Contact\Channels;

use Contact\Dto\ContactMessage;
use WP_Error;

interface ChannelInterface
{
    /** Stable identifier (e.g. "email", "telegram") used in logs and error codes. */
    public function getId(): string;

    /** Whether the admin has toggled this channel on via ACF options. */
    public function isEnabled(): bool;

    /** Whether the admin's credentials/recipient for this channel are valid. */
    public function isConfigured(): bool;

    /**
     * Deliver the message.
     *
     * @return true|WP_Error true on success; WP_Error on transport/API failure.
     */
    public function send(ContactMessage $message): bool|WP_Error;
}
