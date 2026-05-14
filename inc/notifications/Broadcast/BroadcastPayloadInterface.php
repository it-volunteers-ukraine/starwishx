<?php

/**
 * Marker interface for editor-broadcast payloads.
 *
 * Concrete payloads are immutable value objects (see Payloads/*). Channels
 * detect supported types via `instanceof` checks in their compose step;
 * adding a new event type means adding a new payload class plus a compose
 * branch in each channel that should render it.
 *
 * File: inc/notifications/Broadcast/BroadcastPayloadInterface.php
 */

declare(strict_types=1);

namespace Notifications\Broadcast;

interface BroadcastPayloadInterface
{
    /** Stable event identifier (e.g. "opportunity_pending"). Used in logs. */
    public function getEventType(): string;
}
