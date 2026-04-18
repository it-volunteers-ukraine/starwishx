<?php
// File: inc/launchpad/Data/Migrations/CreateOpportunityDetailsTable.php

declare(strict_types=1);

namespace Launchpad\Data\Migrations;

/**
 * Schema for wp_opportunity_details — typed storage for per-opportunity
 * metadata that currently lives as raw strings in wp_postmeta.
 *
 * Why a table, not post_meta:
 * - DATE columns enforce format at the DB layer, closing the garbage-in
 *   hole that post_meta (TEXT) tolerates.
 * - 1 narrow row per post replaces 2 TEXT meta rows per post; future
 *   joins on date are possible without per-row parsing.
 *
 * `is_expired` is derived in PHP by OpportunityDetailsRepository, not
 * stored. MySQL rejects non-deterministic functions (CURDATE/NOW) in
 * generated column expressions, and a DB-side rule would lock us to the
 * MySQL server timezone rather than WP's configured site timezone. If
 * SQL-level filtering on expired status is needed later, the plan is to
 * add a KEY on date_end and filter via `WHERE date_end >= %s` — a DB
 * view remains a later option if multiple external consumers emerge.
 *
 * Dates may be NULL — open-ended opportunities legitimately omit the end.
 * Business rules (e.g., "start requires end") live in the service, not
 * the schema.
 */
class CreateOpportunityDetailsTable
{
    private const VERSION     = '1.0.0';
    private const OPTION_KEY  = 'launchpad_opportunity_details_db_version';
    private const ERROR_KEY   = 'launchpad_opportunity_details_db_error';

    public static function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'opportunity_details';
    }

    public static function run(): void
    {
        global $wpdb;

        $table           = self::tableName();
        $charset_collate = $wpdb->get_charset_collate();

        // dbDelta: exactly two spaces between PRIMARY KEY and (col).
        // No FK — dbDelta doesn't support them reliably; cleanup is hooked
        // in the service layer on delete_post (same pattern as favorites).
        $sql = "CREATE TABLE $table (
            post_id bigint(20) unsigned NOT NULL,
            date_start date DEFAULT NULL,
            date_end date DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (post_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            update_option(self::OPTION_KEY, self::VERSION);
            delete_option(self::ERROR_KEY);
        } else {
            update_option(self::ERROR_KEY, 'Failed to create opportunity_details table');
            error_log('Launchpad Migration Error: opportunity_details table creation failed');
        }
    }

    public static function needsUpgrade(): bool
    {
        return get_option(self::OPTION_KEY) !== self::VERSION;
    }
}
