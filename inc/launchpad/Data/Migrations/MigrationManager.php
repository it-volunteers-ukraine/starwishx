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
        if (!CreateLaunchpadTables::needsUpgrade()) {
            return;
        }
        // Check lock: Another process is running migration
        if (get_transient(self::LOCK_TRANSIENT)) {
            return;
        }
        // Acquire lock
        set_transient(self::LOCK_TRANSIENT, true, self::LOCK_TIMEOUT);
        try {
            CreateLaunchpadTables::run();
        } catch (\Throwable $e) {
            error_log('Launchpad Migration Exception: ' . $e->getMessage());
            update_option('launchpad_db_error', $e->getMessage());
        } finally {
            // Always release lock
            delete_transient(self::LOCK_TRANSIENT);
        }
    }
}
