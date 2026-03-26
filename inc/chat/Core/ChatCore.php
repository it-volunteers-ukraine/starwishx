<?php

/**
 * Chat module
 * Notification center & support messaging panel for Launchpad.
 * Reads from sw_notifications (owned by Notifications module)
 * and renders an activity feed inside the user dashboard.
 *
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * File: inc/chat/Core/ChatCore.php
 */

declare(strict_types=1);

namespace Chat\Core;

use Chat\Services\ActivityService;
use Chat\Api\ActivityController;
use Chat\Panels\ChatPanel;

final class ChatCore
{
    private static ?self $instance = null;
    private ActivityService $activityService;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private function __construct()
    {
        $this->activityService = new ActivityService(
            \notifications()->service()
        );

        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('launchpad_register_panels', [$this, 'registerPanels']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerRestRoutes(): void
    {
        $controller = new ActivityController($this->activityService);
        $controller->registerRoutes();
    }

    public function registerPanels(\Launchpad\Core\PanelRegistry $registry): void
    {
        $registry->register('chat', new ChatPanel($this->activityService), 25);
    }

    public function enqueueAssets(): void
    {
        if (! is_page('launchpad') || ! is_user_logged_in()) {
            return;
        }

        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/chat',
                get_template_directory_uri() . '/assets/js/chat-store.module.js',
                ['@wordpress/interactivity'],
                '1.0.0'
            );
            wp_enqueue_script_module('@starwishx/chat');
        }

        wp_interactivity_state('chat', [
            'config' => [
                'nonce'   => wp_create_nonce('wp_rest'),
                'restUrl' => rest_url('chat/v1/'),
                'messages' => [
                    'loadError'    => __('Failed to load activity.', 'starwishx'),
                    'markError'    => __('Failed to mark as read.', 'starwishx'),
                    'newComment'   => __('left a review on', 'starwishx'),
                    'commentReply' => __('replied to your review on', 'starwishx'),
                    'noActivity'   => __('No notifications yet.', 'starwishx'),
                    'timeAgo'      => [
                        'justNow'  => __('just now', 'starwishx'),
                        'minutes'  => __('%d min ago', 'starwishx'),
                        'hours'    => __('%d hr ago', 'starwishx'),
                        'days'     => __('%d days ago', 'starwishx'),
                    ],
                ],
            ],
        ]);
    }

    public function activityService(): ActivityService
    {
        return $this->activityService;
    }
}
