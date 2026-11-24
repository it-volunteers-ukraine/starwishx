<?php

/**
 * Comments - Independent Module Core
 *
 * Manages the interactive comments/reviews lifecycle: REST API,
 * state hydration, and asset enqueueing.
 * Consumed by: single-opportunity & single-project templates.
 *
 * Version: 0.7.5
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
use Shared\Build\AssetFile;
use WP_Comment;

final class CommentsCore
{
    /** Which AGGREGATES_VERSION the cached ratings were last counted under. */
    private const string AGGREGATES_OPTION = 'starwishx_comments_aggregates_version';

    private static ?self $instance = null;

    public static function instance(
        ?CommentsService $service = null
    ): self {
        return self::$instance ??= new self($service ?? new CommentsService());
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private function __construct(
        private readonly CommentsService $service
    ) {
        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        // REST API
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Asset enqueueing
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // Cached ratings: moderation in wp-admin changes which reviews
        // count, and a change of counting rules recounts them all once.
        add_action('transition_comment_status', [$this, 'onCommentStatusChange'], 10, 3);
        add_action('admin_init', [$this, 'maybeRefreshAllAggregates']);
    }

    public function registerRestRoutes(): void
    {
        new CommentsController($this->service)->registerRoutes();
    }

    /**
     * Approve, unapprove, spam, trash, restore or delete: recount the
     * post's rating. WP passes status names ('approved', 'trash'), never
     * '0'/'1'.
     */
    public function onCommentStatusChange(string $newStatus, string $oldStatus, WP_Comment $comment): void
    {
        $postId = (int) $comment->comment_post_ID;
        if (in_array(get_post_type($postId), CommentsService::ALLOWED_POST_TYPES, true)) {
            $this->service->refreshAggregates($postId);
        }
    }

    /**
     * Recount every rated post once per CommentsService::AGGREGATES_VERSION:
     * on the first wp-admin request after a deploy that changed the rules.
     */
    public function maybeRefreshAllAggregates(): void
    {
        if ((int) get_option(self::AGGREGATES_OPTION, 0) >= CommentsService::AGGREGATES_VERSION) {
            return;
        }

        $this->service->refreshAllAggregates();
        update_option(self::AGGREGATES_OPTION, CommentsService::AGGREGATES_VERSION, true);
    }

    /**
     * Enqueue the comments store JS and hydrate infrastructure config.
     *
     * Post-specific application state (list, aggregates, form fields)
     * is hydrated by the template part (comments-interactive.php).
     */
    public function enqueueAssets(): void
    {
        if (!is_singular(CommentsService::ALLOWED_POST_TYPES)) {
            return;
        }

        // Register the script module
        if (function_exists('wp_register_script_module')) {
            $asset = AssetFile::read('assets/js/comments-store.module.js', ['@wordpress/interactivity']);
            wp_register_script_module(
                '@starwishx/comments',
                $asset['url'],
                $asset['dependencies'],
                $asset['version']
            );
            wp_enqueue_script_module('@starwishx/comments');
        }

        // Infrastructure config — static per request, no post-specific knowledge needed.
        // wp_interactivity_state() merges: the template part (comments-interactive.php)
        // adds application state (comments list, aggregates, form states) on top.
        wp_interactivity_state('comments', ['config' => $this->getConfig()]);
    }

    /**
     * The store's `config`: REST access and the strings only the client
     * shows. Typed on the TS side as CommentsConfig.
     *
     * @return array{nonce: string, restUrl: string, messages: array<string, string>}
     */
    public function getConfig(): array
    {
        return [
            'nonce'    => wp_create_nonce('wp_rest'),
            'restUrl'  => rest_url('comments/v1/'),
            'messages' => [
                'reviewPosted'     => __('Review posted successfully!', 'starwishx'),
                'updateSaved'      => __('Update saved.', 'starwishx'),
                'submitError'      => __('An error occurred while posting.', 'starwishx'),
                /* translators: %avg% = average rating, %count% = number of reviews */
                'ratingBadgeLabel' => __('Average rating: %avg% out of 5 based on %count% reviews', 'starwishx'),
            ],
        ];
    }

    // --- Public Accessors ---

    public function service(): CommentsService
    {
        return $this->service;
    }
}
