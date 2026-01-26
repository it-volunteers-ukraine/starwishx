<?php
// File: inc/launchpad/Data/Migrations/CreateLaunchpadTables.php

declare(strict_types=1);

namespace Launchpad\Data\Migrations;

class CreateLaunchpadTables
{
    private const VERSION = '1.2.0';

    public static function run(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name      = $wpdb->prefix . 'launchpad_favorites';
        // $table_name  = $wpdb->prefix . 'launchpad_statistics';

        // dbDelta requires exact formatting
        // There must be exactly two spaces between PRIMARY KEY and the column definition,
        // e.g., PRIMARY KEY  (id)
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            object_id bigint(20) unsigned NOT NULL,
            object_type varchar(20) NOT NULL DEFAULT 'post',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_object (user_id,object_id,object_type),
            KEY user_id (user_id),
            KEY object_id (object_id)
        ) $charset_collate;";

        //     CREATE TABLE IF NOT EXISTS {$stats_table} (
        //         id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        //         user_id bigint(20) unsigned NOT NULL,
        //         action_type varchar(50) NOT NULL,
        //         object_id bigint(20) unsigned DEFAULT NULL,
        //         metadata longtext DEFAULT NULL,
        //         created_at datetime DEFAULT CURRENT_TIMESTAMP,
        //         PRIMARY KEY (id),
        //         KEY user_id (user_id),
        //         KEY action_type (action_type),
        //         KEY created_at (created_at)
        //     ) {$charset_collate};
        // ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Verify success before updating version
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            update_option('launchpad_db_version', self::VERSION);
            delete_option('launchpad_db_error');
        } else {
            update_option('launchpad_db_error', 'Failed to create favorites table');
            error_log('Launchpad Migration Error: Table creation failed');
        }
    }

    public static function needsUpgrade(): bool
    {
        return get_option('launchpad_db_version') !== self::VERSION;
    }
}
