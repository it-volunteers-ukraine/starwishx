<?php

/**
 * Social Share module — Core Singleton
 *
 * Registers the Interactivity API store, hydrates static config + i18n,
 * and gates asset enqueue to single CPT templates that consume the
 * social share template part.
 *
 * File: inc/social-share/Core/SocialShareCore.php
 */

declare(strict_types=1);

namespace SocialShare\Core;

final class SocialShareCore
{
    public const STATUS_TIMEOUT_MS = 2500;

    private static ?self $instance = null;
    private bool $assetsLoaded     = false;

    public static function instance(): self
    {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Auto-enqueue on single templates that use the share template part.
     * The template part also calls loadAssets() defensively, so this is
     * just a fast path for the common case.
     */
    public function enqueueAssets(): void
    {
        if (! is_singular(['opportunity', 'news', 'project'])) {
            return;
        }

        $this->loadAssets();
    }

    /**
     * Register & enqueue the iAPI module + hydrate state.
     * Idempotent — safe to call from the template part.
     */
    public function loadAssets(): void
    {
        if ($this->assetsLoaded) {
            return;
        }

        $this->assetsLoaded = true;

        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/social-share',
                get_template_directory_uri() . '/assets/js/social-share-store.module.js',
                ['@wordpress/interactivity']
            );
            wp_enqueue_script_module('@starwishx/social-share');
        }

        $this->hydrateState();
    }

    /**
     * Hydrate the iAPI store with static config & i18n.
     * Per-instance UI state (isOpen, showStatus, shareUrl) lives in
     * data-wp-context on the wrapper — see template-parts/social-share.php.
     */
    private function hydrateState(): void
    {
        wp_interactivity_state('social-share', [
            'config' => [
                'statusTimeoutMs' => self::STATUS_TIMEOUT_MS,
            ],
            'i18n' => [
                'copiedLabel' => __('Link copied!', 'starwishx'),
                'copyFailed'  => __('Copy failed', 'starwishx'),
            ],
        ]);
    }
}
