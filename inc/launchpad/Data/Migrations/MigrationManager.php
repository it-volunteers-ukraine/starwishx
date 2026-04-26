<?php
// File: inc/launchpad/Data/Migrations/MigrationManager.php

declare(strict_types=1);

namespace Launchpad\Data\Migrations;

class MigrationManager
{
    private const LOCK_TRANSIENT = 'launchpad_migration_lock';
    private const LOCK_TIMEOUT = 60;
    /**
     * Run migrations if needed (with mutex lock)
     * 
     * This method is idempotent and safe to call multiple times.
     * If another process is running migration, this skips gracefully.
     */
    public static function maybeRunMigrations(): void
    {
        $pending = [];
        if (CreateLaunchpadTables::needsUpgrade()) {
            $pending[] = CreateLaunchpadTables::class;
        }
        if (CreateOpportunityDetailsTable::needsUpgrade()) {
            $pending[] = CreateOpportunityDetailsTable::class;
        }
        // Countries is ordered before the junction so that the seed
        // populates a real referent before any opportunity↔country rows
        // can exist. Junction has no FK, but logical ordering keeps the
        // schema readable for future maintainers.
        if (CreateCountriesTable::needsUpgrade()) {
            $pending[] = CreateCountriesTable::class;
        }
        if (CreateOpportunityCountriesTable::needsUpgrade()) {
            $pending[] = CreateOpportunityCountriesTable::class;
        }
        // Backfill must run after BOTH schema migrations above:
        // sw_countries needs to be populated for slug resolution, and
        // opportunity_countries needs to exist as the write target.
        // Pending array is processed in insertion order, so position
        // here is enough to enforce that ordering.
        if (BackfillOpportunityCountries::needsUpgrade()) {
            $pending[] = BackfillOpportunityCountries::class;
        }

        if (empty($pending)) {
            return;
        }
        // Check lock: Another process is running migration
        if (get_transient(self::LOCK_TRANSIENT)) {
            return;
        }
        // Acquire lock
        set_transient(self::LOCK_TRANSIENT, true, self::LOCK_TIMEOUT);
        try {
            foreach ($pending as $migration) {
                $migration::run();
            }
        } catch (\Throwable $e) {
            error_log('Launchpad Migration Exception: ' . $e->getMessage());
            update_option('launchpad_db_error', $e->getMessage());
        } finally {
            // Always release lock
            delete_transient(self::LOCK_TRANSIENT);
        }
    }
}
