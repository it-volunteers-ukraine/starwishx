<?php
// File: inc/notifications/Data/Migrations/CreateNotificationTables.php

declare(strict_types=1);

namespace Notifications\Data\Migrations;

class CreateNotificationTables
{
    private const VERSION        = '1.1.0';
    private const LOCK_TRANSIENT = 'sw_notifications_migration_lock';
    private const LOCK_TIMEOUT   = 60;

    /**
     * Run migration if needed (with mutex lock).
     * Combines migration + lock logic in a single class since this module
     * has only one table.
     */
    public static function maybeRun(): void
    {
        if (! self::needsUpgrade()) {
            return;
        }

        if (get_transient(self::LOCK_TRANSIENT)) {
            return;
        }

        set_transient(self::LOCK_TRANSIENT, true, self::LOCK_TIMEOUT);

        try {
            self::run();
        } catch (\Throwable $e) {
            error_log('Notifications Migration Exception: ' . $e->getMessage());
            update_option('sw_notifications_db_error', $e->getMessage());
        } finally {
            delete_transient(self::LOCK_TRANSIENT);
        }
    }

    public static function run(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name      = $wpdb->prefix . 'sw_notifications';

        // dbDelta requires exact formatting
        // There must be exactly two spaces between PRIMARY KEY and the column definition
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            recipient_id bigint(20) unsigned NOT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            channel varchar(20) NOT NULL DEFAULT 'email',
            object_id bigint(20) unsigned NOT NULL,
            object_type varchar(20) NOT NULL DEFAULT 'comment',
            context longtext DEFAULT NULL,
            attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime DEFAULT NULL,
            is_read tinyint(1) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY recipient_status (recipient_id, status),
            KEY status_attempts (status, attempts),
            KEY created_at (created_at),
            KEY recipient_read (recipient_id, is_read, created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            update_option('sw_notifications_db_version', self::VERSION);
            delete_option('sw_notifications_db_error');
        } else {
            update_option('sw_notifications_db_error', 'Failed to create sw_notifications table');
            error_log('Notifications Migration Error: Table creation failed');
        }
    }

    public static function needsUpgrade(): bool
    {
        return get_option('sw_notifications_db_version') !== self::VERSION;
    }
}
