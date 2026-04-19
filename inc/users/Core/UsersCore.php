<?php

/**
 * UsersCore — singleton bootstrap for the Users module.
 *
 * Registers:
 *   - user_register           → flag new users as unactivated, detect admin-created
 *   - after_password_reset    → flip is_activated=true after reset_password() commits
 *   - sw_users_daily_cleanup  → cron handler (finds + wipes inactive subscribers)
 *   - sw_log_prune            → daily log retention (30 days)
 *
 * File: inc/users/Core/UsersCore.php
 */

declare(strict_types=1);

namespace Users\Core;

use Shared\Log\Logger;
use Users\Services\UserCleanupService;
use Users\Services\UserStateService;
use WP_User;

final class UsersCore
{
    public const CRON_CLEANUP = 'sw_users_daily_cleanup';
    public const CRON_PRUNE   = 'sw_log_prune';

    private static ?self $instance = null;

    private UserStateService $stateService;
    private UserCleanupService $cleanupService;

    public static function instance(
        ?UserStateService $stateService = null,
        ?UserCleanupService $cleanupService = null
    ): self {
        if (!self::$instance) {
            $stateService   = $stateService   ?? new UserStateService();
            $cleanupService = $cleanupService ?? new UserCleanupService($stateService);

            self::$instance = new self($stateService, $cleanupService);
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private function __construct(
        UserStateService $stateService,
        UserCleanupService $cleanupService
    ) {
        $this->stateService   = $stateService;
        $this->cleanupService = $cleanupService;

        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        // Lifecycle hooks
        add_action('user_register',         [$this, 'onUserRegister'], 10, 1);
        add_action('after_password_reset',  [$this, 'onPasswordReset'], 10, 2);

        // Cron handlers
        add_action(self::CRON_CLEANUP, [$this->cleanupService, 'runCleanup']);
        add_action(self::CRON_PRUNE,   [$this, 'onPruneLogs']);

        // Schedule crons on init (runs after WP-Cron has parsed existing schedules)
        add_action('init', [$this, 'ensureCronSchedules'], 20);
    }

    public function ensureCronSchedules(): void
    {
        if (!wp_next_scheduled(self::CRON_CLEANUP)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_CLEANUP);
        }
        if (!wp_next_scheduled(self::CRON_PRUNE)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_PRUNE);
        }
    }

    /**
     * Fires after wp_insert_user() succeeds in any context (Gateway, wp-admin, WP-CLI).
     * New users start as unactivated. If an administrator triggered the creation
     * (via wp-admin/user-new.php), mark the account so the cleanup cron skips it.
     */
    public function onUserRegister(int $userId): void
    {
        $this->stateService->initAsUnactivated($userId);

        if (function_exists('current_user_can') && current_user_can('create_users')) {
            $this->stateService->markAdminCreated($userId);
        }
    }

    /**
     * Fires after reset_password() commits. Using after_password_reset (not
     * password_reset) avoids flipping the flag if the password save later errors.
     * Clicking the emailed link and successfully setting a password proves
     * email-address ownership — that's the activation signal.
     */
    public function onPasswordReset(WP_User $user, string $newPass): void
    {
        if (!$this->stateService->isActivated($user->ID)) {
            $this->stateService->activate($user->ID, ['trigger' => 'password_reset_link']);
        }
    }

    public function onPruneLogs(): void
    {
        $deleted = Logger::pruneOldLogs();
        if ($deleted > 0) {
            Logger::info('Users', 'Pruned old log files', ['deleted' => $deleted]);
        }
    }

    // --- Public accessors ---

    public function stateService(): UserStateService
    {
        return $this->stateService;
    }

    public function cleanupService(): UserCleanupService
    {
        return $this->cleanupService;
    }
}
