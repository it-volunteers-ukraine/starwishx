<?php
// File: inc/notifications/Data/Repositories/NotificationRepository.php

declare(strict_types=1);

namespace Notifications\Data\Repositories;

class NotificationRepository
{
    private function getTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'sw_notifications';
    }

    /**
     * Insert a new notification row.
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->getTable(),
            [
                'recipient_id' => $data['recipient_id'],
                'actor_id'     => $data['actor_id'],
                'type'         => $data['type'],
                'status'       => 'pending',
                'channel'      => $data['channel'] ?? 'email',
                'object_id'    => $data['object_id'],
                'object_type'  => $data['object_type'] ?? 'comment',
                'context'      => $data['context'] ?? null,
                'created_at'   => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );

        return $result !== false ? (int) $wpdb->insert_id : false;
    }

    /**
     * Fetch pending notifications ready for processing.
     */
    public function fetchPending(int $limit = 20): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->getTable()}
                 WHERE status = 'pending' AND attempts < 3
                 ORDER BY created_at ASC
                 LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Mark a notification as sent.
     */
    public function markSent(int $id): void
    {
        global $wpdb;

        $wpdb->update(
            $this->getTable(),
            [
                'status'  => 'sent',
                'sent_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Increment attempt counter. Mark as failed at 3 attempts.
     */
    public function incrementAttempts(int $id): void
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->getTable()}
                 SET attempts = attempts + 1,
                     status = CASE WHEN attempts + 1 >= 3 THEN 'failed' ELSE status END
                 WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Check for duplicate notification within a time window.
     *
     * @param int    $recipientId
     * @param string $type
     * @param int    $objectId
     * @param int    $windowMinutes Dedup window in minutes (default 60).
     */
    public function findDuplicate(int $recipientId, string $type, int $objectId, int $windowMinutes = 60): bool
    {
        global $wpdb;

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1 FROM {$this->getTable()}
                 WHERE recipient_id = %d
                   AND type = %s
                   AND object_id = %d
                   AND created_at > DATE_SUB(NOW(), INTERVAL %d MINUTE)
                 LIMIT 1",
                $recipientId,
                $type,
                $objectId,
                $windowMinutes
            )
        );

        return (bool) $result;
    }

    /**
     * Purge old sent/failed notifications.
     */
    public function purgeOld(int $days = 30): int
    {
        global $wpdb;

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->getTable()}
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    /**
     * Fetch notifications for a user (newest first).
     * Includes all statuses — the activity feed is decoupled from email delivery.
     */
    public function fetchForRecipient(int $recipientId, int $limit = 15, int $offset = 0): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->getTable()}
                 WHERE recipient_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $recipientId,
                $limit,
                $offset
            )
        );
    }

    /**
     * Count total notifications for a user.
     */
    public function countForRecipient(int $recipientId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->getTable()}
                 WHERE recipient_id = %d",
                $recipientId
            )
        );
    }

    /**
     * Count unread notifications for a user.
     */
    public function countUnreadForRecipient(int $recipientId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->getTable()}
                 WHERE recipient_id = %d AND is_read = 0",
                $recipientId
            )
        );
    }

    /**
     * Mark a single notification as read (with recipient guard).
     */
    public function markRead(int $id, int $recipientId): bool
    {
        global $wpdb;

        $rows = $wpdb->update(
            $this->getTable(),
            ['is_read' => 1],
            ['id' => $id, 'recipient_id' => $recipientId],
            ['%d'],
            ['%d', '%d']
        );

        return $rows !== false && $rows > 0;
    }

    /**
     * Mark all unread notifications as read for a user.
     */
    public function markAllRead(int $recipientId): int
    {
        global $wpdb;

        return (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->getTable()}
                 SET is_read = 1
                 WHERE recipient_id = %d AND is_read = 0",
                $recipientId
            )
        );
    }

    /**
     * Delete all notifications for a user (cleanup on user deletion).
     */
    public function deleteByRecipient(int $userId): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->getTable(),
            ['recipient_id' => $userId],
            ['%d']
        );

        return $result !== false;
    }
}
