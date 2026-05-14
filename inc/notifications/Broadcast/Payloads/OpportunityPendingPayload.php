<?php

/**
 * Payload describing a contributor's submission of an opportunity for review.
 *
 * Built by NotificationService::handleOpportunityPending from the current
 * post state plus the action-payload submitted_at timestamp. The retry path
 * rebuilds it from the post at cron-fire time so a stale payload can't be
 * replayed against a post whose title/url changed in the meantime.
 *
 * File: inc/notifications/Broadcast/Payloads/OpportunityPendingPayload.php
 */

declare(strict_types=1);

namespace Notifications\Broadcast\Payloads;

use Notifications\Broadcast\BroadcastPayloadInterface;

final class OpportunityPendingPayload implements BroadcastPayloadInterface
{
    public function __construct(
        public readonly int $postId,
        public readonly int $submittedAt,
        public readonly string $postTitle,
        public readonly string $postUrl,
        public readonly string $previewUrl,
        public readonly string $editUrl,
        public readonly string $actorDisplayName,
    ) {}

    public function getEventType(): string
    {
        return 'opportunity_pending';
    }
}
