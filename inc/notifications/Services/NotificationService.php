<?php
// File: inc/notifications/Services/NotificationService.php

declare(strict_types=1);

namespace Notifications\Services;

use Notifications\Broadcast\BroadcastPayloadInterface;
use Notifications\Broadcast\EditorBroadcaster;
use Notifications\Broadcast\Payloads\OpportunityPendingPayload;
use Notifications\Channels\EmailChannel;
use Notifications\Channels\NotificationChannelInterface;
use Notifications\Data\Repositories\NotificationRepository;
use Shared\Log\Logger;

class NotificationService
{
    /**
     * Max broadcast attempts (initial + retries). Conservative — beyond this,
     * Telegram is plausibly down for a while and an editor will notice.
     */
    private const BROADCAST_MAX_ATTEMPTS = 3;

    /**
     * Backoff between retries, in seconds. Indexed by attempt number minus 1
     * (so attempt 1 failure schedules attempt 2 after RETRY_DELAYS[0] seconds).
     */
    private const BROADCAST_RETRY_DELAYS = [60, 300];

    /**
     * Transient lock TTL for per-post broadcast dispatch — comfortably above
     * Telegram's request timeout so a long send still finishes inside its
     * own lock.
     */
    private const BROADCAST_LOCK_TTL = 30;

    private NotificationRepository $repository;
    private EditorBroadcaster $broadcaster;

    /** @var NotificationChannelInterface[] */
    private array $channels;

    public function __construct(NotificationRepository $repository, EditorBroadcaster $broadcaster)
    {
        $this->repository  = $repository;
        $this->broadcaster = $broadcaster;
        $this->channels    = [new EmailChannel()];
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
            // Mark 'sent' to prevent reprocessing on the next cron run.
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

    /**
     * Handle the sw_opportunity_pending action.
     *
     * Two parallel flows:
     *  1. Personal email queue — one row per editor (fallback: admins),
     *     event-scoped dedup keyed on the submission timestamp so a contributor
     *     resubmitting after rework notifies editors again.
     *  2. Group broadcast — single dispatch to the editor TG group (if configured),
     *     idempotent per submission attempt via _sw_editor_broadcast_at postmeta.
     *
     * @param int   $postId
     * @param array $context {user_id, submitted_at}
     */
    public function handleOpportunityPending(int $postId, array $context): void
    {
        $actorId     = (int) ($context['user_id'] ?? 0);
        $submittedAt = (int) ($context['submitted_at'] ?? time());
        if ($actorId === 0) {
            return;
        }

        $post = get_post($postId);
        if (! $post || $post->post_type !== 'opportunity') {
            return;
        }

        $actorUser = get_userdata($actorId);
        $actorName = $actorUser ? $actorUser->display_name : __('Someone', 'starwishx');
        $postTitle = get_the_title($post);
        $postUrl   = (string) get_permalink($post);

        // 1) Personal queue — event-scoped dedup
        $recipients  = $this->resolveEditorRecipients();
        $contextJson = wp_json_encode([
            'post_title'         => $postTitle,
            'post_url'           => $postUrl,
            'actor_display_name' => $actorName,
            'submitted_at'       => $submittedAt,
        ]);

        foreach ($recipients as $recipientId) {
            if ($recipientId === $actorId) {
                continue;
            }
            if ($this->repository->findDuplicateSince($recipientId, 'opportunity_pending', $postId, $submittedAt)) {
                continue;
            }

            $this->repository->create([
                'recipient_id' => $recipientId,
                'actor_id'     => $actorId,
                'type'         => 'opportunity_pending',
                'channel'      => 'email',
                'object_id'    => $postId,
                'object_type'  => 'post',
                'context'      => $contextJson,
            ]);
        }

        if (! empty($recipients) && ! wp_next_scheduled('sw_process_notifications')) {
            wp_schedule_single_event(time(), 'sw_process_notifications');
        }

        // 2) Group broadcast — first attempt; retries scheduled via cron on transient failure
        $payload = new OpportunityPendingPayload(
            postId: $postId,
            submittedAt: $submittedAt,
            postTitle: $postTitle,
            postUrl: $postUrl,
            previewUrl: get_preview_post_link($postId),
            editUrl: admin_url("post.php?post={$postId}&action=edit"),
            actorDisplayName: $actorName,
        );
        $this->attemptBroadcast($payload, 1);
    }

    /**
     * WP Cron handler for retrying a previously-failed editor broadcast.
     * Rebuilds the payload from current post state so a stale snapshot
     * can't be replayed against a renamed/moved post.
     */
    public function retryEditorBroadcast(int $postId, int $submittedAt, int $attempt): void
    {
        // A newer submission has since broadcast successfully — abort this chain.
        $lastBroadcast = (int) get_post_meta($postId, '_sw_editor_broadcast_at', true);
        if ($lastBroadcast >= $submittedAt) {
            return;
        }

        $payload = $this->buildOpportunityPendingPayload($postId, $submittedAt);
        if ($payload === null) {
            return;
        }

        $this->attemptBroadcast($payload, $attempt);
    }

    /**
     * Attempt one broadcast dispatch. On transient failure, schedule the next
     * attempt via cron. On permanent failure or after exhausting attempts,
     * record a failure marker and log.
     */
    private function attemptBroadcast(BroadcastPayloadInterface $payload, int $attempt): void
    {
        if (! $payload instanceof OpportunityPendingPayload) {
            // Retry plumbing is shaped around OpportunityPendingPayload's
            // postId/submittedAt keys; future payload types need their own.
            return;
        }

        $postId      = $payload->postId;
        $submittedAt = $payload->submittedAt;

        // Idempotency: a successful broadcast already covered this submission.
        $lastBroadcast = (int) get_post_meta($postId, '_sw_editor_broadcast_at', true);
        if ($lastBroadcast >= $submittedAt) {
            return;
        }

        if (! $this->acquireBroadcastLock($postId)) {
            Logger::debug('Notifications', 'Broadcast lock held, deferring', [
                'postId'  => $postId,
                'attempt' => $attempt,
            ]);
            return;
        }

        try {
            $result = $this->broadcaster->dispatch($payload);

            if ($result['succeeded'] > 0) {
                update_post_meta($postId, '_sw_editor_broadcast_at', time());
                return;
            }

            // No channels were enabled+configured — admin choice, not a failure.
            if ($result['attempted'] === 0) {
                return;
            }

            if ($result['retryable'] && $attempt < self::BROADCAST_MAX_ATTEMPTS) {
                $delay = self::BROADCAST_RETRY_DELAYS[$attempt - 1] ?? 300;
                wp_schedule_single_event(
                    time() + $delay,
                    'sw_retry_editor_broadcast',
                    [$postId, $submittedAt, $attempt + 1]
                );
                return;
            }

            // Permanent failure or attempts exhausted.
            update_post_meta($postId, '_sw_editor_broadcast_failed_at', time());
            Logger::error('Notifications', 'Broadcast permanently failed', [
                'postId'   => $postId,
                'attempt'  => $attempt,
                'failures' => $result['failures'],
            ]);
        } finally {
            $this->releaseBroadcastLock($postId);
        }
    }

    /**
     * Rebuild a payload at retry time from current post state.
     * Returns null if the post no longer exists or has changed type.
     */
    private function buildOpportunityPendingPayload(int $postId, int $submittedAt): ?OpportunityPendingPayload
    {
        $post = get_post($postId);
        if (! $post || $post->post_type !== 'opportunity') {
            return null;
        }

        $authorId  = (int) $post->post_author;
        $actorUser = $authorId > 0 ? get_userdata($authorId) : null;
        $actorName = $actorUser ? $actorUser->display_name : __('Someone', 'starwishx');

        return new OpportunityPendingPayload(
            postId: $postId,
            submittedAt: $submittedAt,
            postTitle: get_the_title($post),
            postUrl: (string) get_permalink($post),
            previewUrl: get_preview_post_link($postId),
            editUrl: admin_url("post.php?post={$postId}&action=edit"),
            actorDisplayName: $actorName,
        );
    }

    /**
     * Try to take the per-post broadcast lock. Returns false if another
     * dispatch holds it. The TTL guarantees the lock auto-expires if a
     * holder crashes mid-send.
     */
    private function acquireBroadcastLock(int $postId): bool
    {
        $key = 'sw_eb_lock_' . $postId;
        if (get_transient($key) !== false) {
            return false;
        }
        set_transient($key, time(), self::BROADCAST_LOCK_TTL);
        return true;
    }

    private function releaseBroadcastLock(int $postId): void
    {
        delete_transient('sw_eb_lock_' . $postId);
    }

    /**
     * Handle the sw_opportunity_published action.
     *
     * Notifies the contributor (post author) anonymously — the editor's
     * identity is intentionally not surfaced, to prevent targeted abuse if
     * a contributor takes issue with editorial decisions.
     *
     * @param int   $postId
     * @param array $context {actor_id, author_id}
     */
    public function handleOpportunityPublished(int $postId, array $context): void
    {
        $actorId  = (int) ($context['actor_id'] ?? 0);
        $authorId = (int) ($context['author_id'] ?? 0);
        if ($authorId === 0 || $actorId === $authorId) {
            return;
        }

        $post = get_post($postId);
        if (! $post || $post->post_type !== 'opportunity') {
            return;
        }

        // Race-protection dedup only — duplicates here would be double-clicks.
        if ($this->repository->findDuplicate($authorId, 'opportunity_published', $postId)) {
            return;
        }

        $contextJson = wp_json_encode([
            'post_title'         => get_the_title($post),
            'post_url'           => (string) get_permalink($post),
            'actor_display_name' => '',  // intentionally anonymous
        ]);

        $this->repository->create([
            'recipient_id' => $authorId,
            'actor_id'     => 0,  // anonymised — actor name never reaches the recipient
            'type'         => 'opportunity_published',
            'channel'      => 'email',
            'object_id'    => $postId,
            'object_type'  => 'post',
            'context'      => $contextJson,
        ]);

        if (! wp_next_scheduled('sw_process_notifications')) {
            wp_schedule_single_event(time(), 'sw_process_notifications');
        }
    }

    /**
     * Handle the sw_opportunity_returned_to_draft action.
     *
     * Notifies the contributor anonymously that revisions are needed.
     * Covers both pending→draft (rejected from review) and publish→draft
     * (demoted from live) — the contributor's next action is the same:
     * edit and resubmit.
     *
     * @param int   $postId
     * @param array $context {actor_id, author_id}
     */
    public function handleOpportunityReturnedToDraft(int $postId, array $context): void
    {
        $actorId  = (int) ($context['actor_id'] ?? 0);
        $authorId = (int) ($context['author_id'] ?? 0);
        if ($authorId === 0 || $actorId === $authorId) {
            return;
        }

        $post = get_post($postId);
        if (! $post || $post->post_type !== 'opportunity') {
            return;
        }

        if ($this->repository->findDuplicate($authorId, 'opportunity_returned_to_draft', $postId)) {
            return;
        }

        // Frontend launchpad URL — contributors edit drafts via the SPA, not wp-admin.
        $pageId = (int) get_option('launchpad_page_id');
        $url    = $pageId > 0 ? (string) (get_permalink($pageId) ?: '') : '';
        if ($url === '') {
            $url = home_url('/launchpad/');
        }

        $contextJson = wp_json_encode([
            'post_title'         => get_the_title($post),
            'post_url'           => $url,
            'actor_display_name' => '',
        ]);

        $this->repository->create([
            'recipient_id' => $authorId,
            'actor_id'     => 0,
            'type'         => 'opportunity_returned_to_draft',
            'channel'      => 'email',
            'object_id'    => $postId,
            'object_type'  => 'post',
            'context'      => $contextJson,
        ]);

        if (! wp_next_scheduled('sw_process_notifications')) {
            wp_schedule_single_event(time(), 'sw_process_notifications');
        }
    }

    /**
     * Resolve editor recipients. Falls back to administrators if no editors found.
     *
     * @return int[] User IDs
     */
    private function resolveEditorRecipients(): array
    {
        $editors = get_users(['role' => 'editor', 'fields' => 'ID']);

        if (!empty($editors)) {
            return array_map('intval', $editors);
        }

        // Fallback to administrators
        $admins = get_users(['role' => 'administrator', 'fields' => 'ID']);
        return array_map('intval', $admins);
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
