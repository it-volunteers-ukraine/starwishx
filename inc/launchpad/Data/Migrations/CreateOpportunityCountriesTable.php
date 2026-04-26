<?php
// File: inc/launchpad/Data/Migrations/CreateOpportunityCountriesTable.php

declare(strict_types=1);

namespace Launchpad\Data\Migrations;

/**
 * Junction table: opportunity (post_id) ↔ country (country_id).
 *
 * Replaces the WP `country` taxonomy assignment for the `opportunity`
 * CPT. Many-to-many: an opportunity may apply to several countries.
 *
 * Composite primary key on (post_id, country_id) prevents duplicate
 * associations and gives a covering index for the forward direction
 * ("which countries are tied to opportunity X"). The separate KEY on
 * country_id covers the reverse direction ("which opportunities are
 * tied to country Y").
 *
 * No FKs — dbDelta doesn't support them reliably; cascading cleanup is
 * the service layer's responsibility on `delete_post` and on
 * sw_country deletion (same pattern as wp_opportunity_details and
 * wp_launchpad_favorites).
 */
class CreateOpportunityCountriesTable
{
    private const VERSION    = '1.0.0';
    private const OPTION_KEY = 'launchpad_opportunity_countries_db_version';
    private const ERROR_KEY  = 'launchpad_opportunity_countries_db_error';

    public static function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'opportunity_countries';
    }

    public static function run(): void
    {
        global $wpdb;

        $table           = self::tableName();
        $charset_collate = $wpdb->get_charset_collate();

        // dbDelta: exactly two spaces between PRIMARY KEY and (col).
        $sql = "CREATE TABLE $table (
            post_id bigint(20) unsigned NOT NULL,
            country_id int(10) unsigned NOT NULL,
            PRIMARY KEY  (post_id,country_id),
            KEY country_id (country_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            update_option(self::OPTION_KEY, self::VERSION);
            delete_option(self::ERROR_KEY);
        } else {
            update_option(self::ERROR_KEY, 'Failed to create opportunity_countries table');
            error_log('Launchpad Migration Error: opportunity_countries table creation failed');
        }
    }

    public static function needsUpgrade(): bool
    {
        return get_option(self::OPTION_KEY) !== self::VERSION;
    }
}
