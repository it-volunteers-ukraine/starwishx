<?php
// File: inc/notifications/Services/NotificationService.php

declare(strict_types=1);

namespace Notifications\Services;

use Notifications\Channels\EmailChannel;
use Notifications\Channels\NotificationChannelInterface;
use Notifications\Data\Repositories\NotificationRepository;

class NotificationService
{
    private NotificationRepository $repository;

    /** @var NotificationChannelInterface[] */
    private array $channels;

    public function __construct(NotificationRepository $repository)
    {
        $this->repository = $repository;
        $this->channels   = [new EmailChannel()];
    }

    /**
     * Handle the sw_comment_posted action.
     * Resolves the recipient and enqueues a notification.
     *
     * @param int   $commentId
     * @param array $context {user_id, post_id, parent_id, rating}
     */
    public function handleCommentPosted(int $commentId, array $context): void
    {
        $actorId  = (int) ($context['user_id'] ?? 0);
        $postId   = (int) ($context['post_id'] ?? 0);
        $parentId = (int) ($context['parent_id'] ?? 0);

        if ($actorId === 0 || $postId === 0) {
            return;
        }

        $post = get_post($postId);
        if (! $post) {
            return;
        }

        // Determine type and recipient
        if ($parentId === 0) {
            // Top-level comment → notify post author
            $type        = 'new_comment';
            $recipientId = (int) $post->post_author;
        } else {
            // Reply → notify parent comment author
            $type          = 'comment_reply';
            $parentComment = get_comment($parentId);
            if (! $parentComment) {
                return;
            }
            $recipientId = (int) $parentComment->user_id;
        }

        // Self-notification guard
        if ($recipientId === $actorId) {
            return;
        }

        // Recipient validity
        $recipientUser = get_userdata($recipientId);
        if (! $recipientUser) {
            return;
        }

        // Deduplication: 1 hour window per recipient + type + object_id
        if ($this->repository->findDuplicate($recipientId, $type, $commentId)) {
            return;
        }

        // Build context snapshot
        $comment      = get_comment($commentId);
        $actorUser    = get_userdata($actorId);
        $contextJson  = wp_json_encode([
            'post_title'         => get_the_title($post),
            'post_url'           => get_permalink($post),
            'comment_excerpt'    => wp_trim_words($comment->comment_content ?? '', 30, '...'),
            'actor_display_name' => $actorUser ? $actorUser->display_name : __('Someone', 'starwishx'),
            'rating'             => $context['rating'] ?? 0,
        ]);

        // Enqueue
        $this->repository->create([
            'recipient_id' => $recipientId,
            'actor_id'     => $actorId,
            'type'         => $type,
            'channel'      => 'email',
            'object_id'    => $commentId,
            'object_type'  => 'comment',
            'context'      => $contextJson,
        ]);

        // Schedule near-instant processing
        if (! wp_next_scheduled('sw_process_notifications')) {
            wp_schedule_single_event(time(), 'sw_process_notifications');
        }
    }

    /**
     * Process the pending notification queue.
     * Called by WP Cron (both immediate single events and the safety-net recurring schedule).
     */
    public function processQueue(): void
    {
        $pending = $this->repository->fetchPending(20);

        foreach ($pending as $notification) {
            // Recipient preference: skip email if opted out.
            // The row is still marked 'sent' so it appears in the Chat activity feed.
            $receiveNotifications = get_field(
                'receive_mail_notifications',
                'user_' . $notification->recipient_id
            );
            if ($receiveNotifications === false || (int) $receiveNotifications === 0) {
                $this->repository->markSent((int) $notification->id);
                continue;
            }

            $sent = false;

            foreach ($this->channels as $channel) {
                if ($channel->supports($notification->channel)) {
                    $sent = $channel->send($notification);
                    break;
                }
            }

            if ($sent) {
                $this->repository->markSent((int) $notification->id);
            } else {
                $this->repository->incrementAttempts((int) $notification->id);
            }
        }

        // Housekeeping: purge old rows
        $this->repository->purgeOld(30);
    }

    /**
     * Cleanup all notifications for a deleted user.
     */
    public function cleanupUser(int $userId): void
    {
        $this->repository->deleteByRecipient($userId);
    }

    // --- User-facing read methods (consumed by Chat module) ---

    public function getForRecipient(int $userId, int $limit = 15, int $offset = 0): array
    {
        return $this->repository->fetchForRecipient($userId, $limit, $offset);
    }

    public function countForRecipient(int $userId): int
    {
        return $this->repository->countForRecipient($userId);
    }

    public function countUnreadForRecipient(int $userId): int
    {
        return $this->repository->countUnreadForRecipient($userId);
    }

    public function markRead(int $id, int $userId): bool
    {
        return $this->repository->markRead($id, $userId);
    }

    public function markAllRead(int $userId): int
    {
        return $this->repository->markAllRead($userId);
    }
}
