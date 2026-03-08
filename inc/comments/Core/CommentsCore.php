<?php

/**
 * Comments - Independent Module Core
 *
 * Manages the interactive comments/reviews lifecycle: REST API,
 * state hydration, and asset enqueueing.
 * Consumed by: single-opportunity & single-project templates.
 *
 * Version: 0.7.1
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * File: inc/comments/Core/CommentsCore.php
 */

declare(strict_types=1);

namespace Comments\Core;

use Comments\Services\CommentsService;
use Comments\Api\CommentsController;

final class CommentsCore
{
    private static ?self $instance = null;

    private CommentsService $service;

    public static function instance(
        ?CommentsService $service = null
    ): self {
        if (!self::$instance) {
            $service = $service ?? new CommentsService();
            self::$instance = new self($service);
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private function __construct(CommentsService $service)
    {
        $this->service = $service;
        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        // REST API
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Asset enqueueing
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerRestRoutes(): void
    {
        (new CommentsController($this->service))->registerRoutes();
    }

    /**
     * Enqueue the comments store JS and hydrate infrastructure config.
     *
     * Post-specific application state (list, aggregates, form fields)
     * is hydrated by the template part (comments-interactive.php).
     */
    public function enqueueAssets(): void
    {
        $needsComments = is_singular('opportunity')
            || is_singular('project');

        if (!$needsComments || !is_user_logged_in()) {
            return;
        }

        // Register the script module
        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/comments',
                get_template_directory_uri() . '/assets/js/comments-store.module.js',
                ['@wordpress/interactivity']
            );
            wp_enqueue_script_module('@starwishx/comments');
        }

        // Infrastructure config — static per request, no post-specific knowledge needed.
        // wp_interactivity_state() merges: the template part (comments-interactive.php)
        // adds application state (comments list, aggregates, form states) on top.
        wp_interactivity_state('comments', [
            'config' => [
                'nonce'    => wp_create_nonce('wp_rest'),
                'restUrl'  => rest_url('comments/v1/'),
                'messages' => [
                    'reviewPosted'  => __('Review posted successfully!', 'starwishx'),
                    'updateSaved'   => __('Update saved.', 'starwishx'),
                    'submitError'   => __('An error occurred while posting.', 'starwishx'),
                ],
            ],
        ]);
    }

    // --- Public Accessors ---

    public function service(): CommentsService
    {
        return $this->service;
    }
}
