<?php

/**
 * UserCleanupService — finds and wipes inactive (bot-suspected) accounts.
 *
 * Safety rules baked into the query:
 *   - role__in = ['subscriber']                   higher roles never candidates
 *   - is_activated = '0' (exact match, NOT NOT-EXISTS) only users flagged
 *                                                   by this module; legacy users safe
 *   - sw_user_source != 'admin'                   admin-created accounts permanently skipped
 *   - user_registered < now - $olderThanDays days
 *   - number = $batchSize                         next cron picks up the rest
 *
 * Idempotency: runCleanup() is guarded by a transient lock so overlapping
 * WP-Cron fires cannot double-wipe.
 *
 * File: inc/users/Services/UserCleanupService.php
 */

declare(strict_types=1);

namespace Users\Services;

use Shared\Log\Logger;
use WP_User_Query;

final class UserCleanupService
{
    public const LOCK_KEY = 'sw_users_cleanup_running';
    public const LOCK_TTL = 10 * MINUTE_IN_SECONDS;

    public const DEFAULT_THRESHOLD_DAYS = 2;
    public const DEFAULT_BATCH_SIZE     = 50;

    private UserStateService $stateService;

    public function __construct(UserStateService $stateService)
    {
        $this->stateService = $stateService;
    }

    /**
     * @return int[] User IDs that match the inactive-account criteria.
     */
    public function findInactiveAccounts(
        int $olderThanDays = self::DEFAULT_THRESHOLD_DAYS,
        int $batchSize = self::DEFAULT_BATCH_SIZE
    ): array {
        $threshold = gmdate('Y-m-d H:i:s', time() - ($olderThanDays * DAY_IN_SECONDS));

        $query = new WP_User_Query([
            'role__in'    => ['subscriber'],
            'number'      => $batchSize,
            'fields'      => 'ID',
            'date_query'  => [
                [
                    'column' => 'user_registered',
                    'before' => $threshold,
                    'inclusive' => true,
                ],
            ],
            'meta_query'  => [
                'relation' => 'AND',
                [
                    'key'     => UserStateService::META_ACTIVATED,
                    'value'   => '0',
                    'compare' => '=',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'     => UserStateService::META_SOURCE,
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => UserStateService::META_SOURCE,
                        'value'   => UserStateService::SOURCE_ADMIN,
                        'compare' => '!=',
                    ],
                ],
            ],
        ]);

        return array_map('intval', (array) $query->get_results());
    }

    /**
     * Delete a user account. WordPress fires the `delete_user` action,
     * which cascades to favorites + notifications cleanup via their
     * existing listeners — no extra work here.
     */
    public function wipeAccount(int $userId, string $reason): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = get_userdata($userId);
        if (!$user) {
            return false;
        }

        // wp_delete_user is defined in wp-admin/includes/user.php — cron context
        // doesn't load admin includes by default, so pull it in defensively.
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $context = [
            'userId'     => $userId,
            'email'      => $user->user_email,
            'login'      => $user->user_login,
            'registered' => $user->user_registered,
            'reason'     => $reason,
        ];

        $deleted = wp_delete_user($userId, null);

        if ($deleted) {
            Logger::info('Users', 'Wiped inactive account', $context);
        } else {
            Logger::error('Users', 'Failed to wipe inactive account', $context);
        }

        return (bool) $deleted;
    }

    /**
     * Cron entry point — transient-locked, batch-limited.
     */
    public function runCleanup(): void
    {
        if (get_transient(self::LOCK_KEY)) {
            return;
        }
        set_transient(self::LOCK_KEY, 1, self::LOCK_TTL);

        try {
            $ids = $this->findInactiveAccounts();
            if (empty($ids)) {
                return;
            }

            $wiped = 0;
            foreach ($ids as $userId) {
                if ($this->wipeAccount($userId, 'inactive_over_2_days')) {
                    $wiped++;
                }
            }

            Logger::info('Users', 'Cleanup batch complete', [
                'candidates' => count($ids),
                'wiped'      => $wiped,
            ]);
        } finally {
            delete_transient(self::LOCK_KEY);
        }
    }
}
