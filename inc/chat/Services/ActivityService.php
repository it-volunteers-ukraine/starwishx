<?php
// File: inc/chat/Services/ActivityService.php

declare(strict_types=1);

namespace Chat\Services;

use Notifications\Services\NotificationService;

class ActivityService
{
    private NotificationService $notificationService;
    private const PER_PAGE = 15;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get paginated activity feed for a user.
     */
    public function getActivity(int $userId, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $offset = ($page - 1) * $perPage;
        $rows   = $this->notificationService->getForRecipient($userId, $perPage, $offset);
        $total  = $this->notificationService->countForRecipient($userId);

        return [
            'items'      => array_map([$this, 'formatItem'], $rows),
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
            'hasMore'    => ($page * $perPage) < $total,
        ];
    }

    public function getUnreadCount(int $userId): int
    {
        return $this->notificationService->countUnreadForRecipient($userId);
    }

    public function markRead(int $notificationId, int $userId): bool
    {
        return $this->notificationService->markRead($notificationId, $userId);
    }

    public function markAllRead(int $userId): int
    {
        return $this->notificationService->markAllRead($userId);
    }

    /**
     * Format a notification row for the activity feed UI.
     * Context JSON is captured at enqueue time by NotificationService::handleCommentPosted.
     */
    private function formatItem(object $row): array
    {
        $context = json_decode($row->context, true) ?: [];
        $actorId = (int) $row->actor_id;

        return [
            'id'             => (int) $row->id,
            'type'           => $row->type,
            'isRead'         => (bool) ($row->is_read ?? false),
            'actorName'      => $context['actor_display_name'] ?? '',
            'actorAvatar'    => get_avatar_url($actorId, ['size' => 40]) ?: '',
            'postTitle'      => $context['post_title'] ?? '',
            'postUrl'        => $context['post_url'] ?? '',
            'commentExcerpt' => $context['comment_excerpt'] ?? '',
            'rating'         => (int) ($context['rating'] ?? 0),
            'createdAt'      => $row->created_at,
        ];
    }
}
