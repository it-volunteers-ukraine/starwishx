<?php

/**
 * UserStateService — owns is_activated + moderation_status reads/writes
 * and a separate sw_user_source flag used by the cleanup job to skip
 * admin-created accounts.
 *
 * Meta-value convention: values are stored as '1' / '0' / '' to stay
 * interoperable with ACF's true_false field and with meta_query IN/=
 * comparisons. Legacy users that predate this module have no meta value
 * set — isActivated() treats them as activated (grandfathered in) so the
 * login gate never locks them out.
 *
 * File: inc/users/Services/UserStateService.php
 */

declare(strict_types=1);

namespace Users\Services;

use Shared\Log\Logger;

final class UserStateService
{
    public const META_ACTIVATED  = 'is_activated';
    public const META_SOURCE     = 'sw_user_source';
    public const META_MODERATION = 'moderation_status';

    public const SOURCE_ADMIN = 'admin';

    public const MODERATION_DEFAULT = 'normal';

    /**
     * True when the user is either explicitly activated ('1') or grandfathered
     * in (no meta set — predates this module). Returns false only when the
     * meta is explicitly '0' (set by initAsUnactivated at registration).
     */
    public function isActivated(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $meta = get_user_meta($userId, self::META_ACTIVATED, true);

        // '' => meta never set (pre-module user) → treat as activated
        // '1' => explicitly activated
        // '0' => explicitly not activated
        return $meta === '' || $meta === '1';
    }

    public function activate(int $userId, array $context = []): void
    {
        if ($userId <= 0) {
            return;
        }

        update_user_meta($userId, self::META_ACTIVATED, '1');

        Logger::info('Users', 'Account activated', array_merge(
            ['userId' => $userId],
            $context
        ));
    }

    public function initAsUnactivated(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        update_user_meta($userId, self::META_ACTIVATED, '0');
    }

    public function markAdminCreated(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        update_user_meta($userId, self::META_SOURCE, self::SOURCE_ADMIN);
    }

    public function isAdminCreated(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return get_user_meta($userId, self::META_SOURCE, true) === self::SOURCE_ADMIN;
    }

    /**
     * Scaffolded for future moderation enforcement. Returns 'normal' when no
     * explicit status is set. Not consumed anywhere yet — Comments/Opportunities
     * will call this in a later iteration.
     */
    public function getModerationStatus(int $userId): string
    {
        if ($userId <= 0) {
            return self::MODERATION_DEFAULT;
        }

        $status = (string) get_user_meta($userId, self::META_MODERATION, true);
        return $status !== '' ? $status : self::MODERATION_DEFAULT;
    }
}
