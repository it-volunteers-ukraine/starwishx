<?php

/**
 * Async notification queue for comment events.
 * Delivers via email; extensible to Telegram and other channels.
 *
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * File: inc/notifications/Core/NotificationsCore.php
 */


declare(strict_types=1);

namespace Notifications\Core;

use Notifications\Data\Repositories\NotificationRepository;
use Notifications\Services\NotificationService;

class NotificationsCore
{
    private static ?self $instance = null;
    private NotificationService $service;

    private function __construct(NotificationService $service)
    {
        $this->service = $service;
        $this->bootstrap();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            $repository = new NotificationRepository();
            $service    = new NotificationService($repository);

            self::$instance = new self($service);
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private function bootstrap(): void
    {
        // Listen for comment notifications (fired by CommentsService)
        add_action('sw_comment_posted', [$this->service, 'handleCommentPosted'], 10, 2);

        // WP Cron action for queue processing
        add_action('sw_process_notifications', [$this->service, 'processQueue']);

        // Register 5-minute safety-net cron schedule
        add_filter('cron_schedules', [$this, 'addCronSchedule']);

        if (! wp_next_scheduled('sw_process_notifications_recurring')) {
            wp_schedule_event(time(), 'sw_every_five_minutes', 'sw_process_notifications_recurring');
        }
        add_action('sw_process_notifications_recurring', [$this->service, 'processQueue']);

        // Cleanup on user deletion
        add_action('delete_user', [$this->service, 'cleanupUser']);
    }

    /**
     * Add a custom 5-minute cron interval.
     */
    public function addCronSchedule(array $schedules): array
    {
        $schedules['sw_every_five_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __('Every Five Minutes', 'starwishx'),
        ];

        return $schedules;
    }

    public function service(): NotificationService
    {
        return $this->service;
    }
}
