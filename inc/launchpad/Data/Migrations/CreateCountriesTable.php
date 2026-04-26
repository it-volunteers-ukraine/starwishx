<?php
// File: inc/launchpad/Data/Migrations/CreateCountriesTable.php

declare(strict_types=1);

namespace Launchpad\Data\Migrations;

/**
 * Schema for wp_sw_countries — curated country list backing the
 * `country` field on opportunities (replacing the WP `country` taxonomy).
 *
 * Why a table, not a taxonomy:
 * - `id` is ISO 3166-1 numeric (e.g. 804 for Ukraine) — globally
 *   meaningful, identical across environments. Term IDs are auto-
 *   increment and differ between dev/staging/prod, which makes
 *   exports, analytics, and future API integrations brittle.
 * - One row, two name columns (uk + en) replaces per-language
 *   taxonomy duplicates. Sort order is decided at row level via
 *   `priority`, not by hand-curated term order.
 * - `code` / `code3` give us alpha-2 / alpha-3 lookups without
 *   maintaining a separate slug → ISO map.
 *
 * Seeded once on first install from inc/launchpad/Data/seed/countries.php
 * (184 rows: 193 ISO entries minus 9 sanctioned exclusions). Subsequent
 * curated changes (new ISO entries, priority tweaks) belong in their own
 * bumped-VERSION migration so the diff is reviewable instead of silently
 * overwriting admin-curated state.
 */
class CreateCountriesTable
{
    private const VERSION    = '1.0.0';
    private const OPTION_KEY = 'launchpad_countries_db_version';
    private const ERROR_KEY  = 'launchpad_countries_db_error';
    private const SEED_FILE  = __DIR__ . '/../seed/countries.php';

    public static function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'sw_countries';
    }

    public static function run(): void
    {
        global $wpdb;

        $table           = self::tableName();
        $charset_collate = $wpdb->get_charset_collate();

        // dbDelta: exactly two spaces between PRIMARY KEY and (col).
        // Index names mirror countries/sw_countries.sql so a hand-imported
        // database and a migration-built database have identical schemas.
        $sql = "CREATE TABLE $table (
            id int(10) unsigned NOT NULL,
            code char(2) NOT NULL,
            code3 char(3) NOT NULL,
            name varchar(75) NOT NULL,
            name_en varchar(75) NOT NULL,
            priority smallint(5) unsigned NOT NULL DEFAULT 1000,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_countries_code (code),
            UNIQUE KEY uq_countries_code3 (code3),
            KEY idx_countries_priority (priority)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            self::seed();
            update_option(self::OPTION_KEY, self::VERSION);
            delete_option(self::ERROR_KEY);
        } else {
            update_option(self::ERROR_KEY, 'Failed to create sw_countries table');
            error_log('Launchpad Migration Error: sw_countries table creation failed');
        }
    }

    /**
     * Seed only when the table is empty.
     *
     * Once an admin has touched the table (added rows, edited names,
     * retuned priority), we never overwrite. Future curated updates
     * belong in their own bumped-VERSION migration.
     *
     * INSERT IGNORE so a partially-seeded table (rare crash mid-loop)
     * can be safely re-attempted without unique-key collisions on
     * already-inserted rows.
     */
    private static function seed(): void
    {
        global $wpdb;
        $table = self::tableName();

        $existing = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($existing > 0) {
            return;
        }

        if (!file_exists(self::SEED_FILE)) {
            error_log('Launchpad Migration Warning: countries seed file missing');
            return;
        }

        $rows = require self::SEED_FILE;
        if (!is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            if (!is_array($row) || count($row) < 6) {
                continue;
            }
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $table (id, code, code3, name, name_en, priority) VALUES (%d, %s, %s, %s, %s, %d)",
                (int) $row[0],
                (string) $row[1],
                (string) $row[2],
                (string) $row[3],
                (string) $row[4],
                (int) $row[5]
            ));
        }
    }

    public static function needsUpgrade(): bool
    {
        return get_option(self::OPTION_KEY) !== self::VERSION;
    }
}
