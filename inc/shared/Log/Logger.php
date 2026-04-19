<?php

/**
 * Shared\Log\Logger — lightweight file-based application logger.
 *
 * Writes to wp-content/uploads/sw/app-YYYY-MM-DD.log.
 * Date-based filenames avoid rename-race hazards that plague size-based
 * rotation under concurrent PHP processes. A daily prune job (registered
 * by UsersCore) removes files older than 30 days.
 *
 * Method signatures deliberately mirror Psr\Log\LoggerInterface so a future
 * swap to Monolog requires only adapter glue, not a call-site rewrite.
 *
 * File: inc/shared/Log/Logger.php
 */

declare(strict_types=1);

namespace Shared\Log;

final class Logger
{
    private const DIR = 'sw';

    public static function info(string $module, string $message, array $context = []): void
    {
        self::write('INFO', $module, $message, $context);
    }

    public static function warning(string $module, string $message, array $context = []): void
    {
        self::write('WARNING', $module, $message, $context);
    }

    public static function error(string $module, string $message, array $context = []): void
    {
        self::write('ERROR', $module, $message, $context);
    }

    public static function debug(string $module, string $message, array $context = []): void
    {
        if (!(defined('WP_DEBUG') && WP_DEBUG)) {
            return;
        }
        self::write('DEBUG', $module, $message, $context);
    }

    /**
     * Delete log files older than the given retention window.
     * Called by the daily sw_log_prune cron.
     *
     * @return int Number of files deleted.
     */
    public static function pruneOldLogs(int $daysToKeep = 30): int
    {
        $dir = self::logDirectory();
        if ($dir === null || !is_dir($dir)) {
            return 0;
        }

        $threshold = time() - ($daysToKeep * DAY_IN_SECONDS);
        $deleted = 0;

        foreach ((array) glob($dir . '/app-*.log') as $file) {
            if (is_file($file) && filemtime($file) < $threshold) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private static function write(string $level, string $module, string $message, array $context): void
    {
        $path = self::ensureTodayPath();
        if ($path === null) {
            // Filesystem unavailable — degrade to PHP error log so nothing is lost silently.
            error_log(sprintf('[%s] [%s] %s', $level, $module, $message));
            return;
        }

        $ctx = !empty($context) ? ' ' . wp_json_encode($context) : '';
        $line = sprintf("[%s] [%s] [%s] %s%s\n", gmdate('c'), $level, $module, $message, $ctx);

        // error_log with type=3 performs an atomic append per call — safe under concurrent writes.
        @error_log($line, 3, $path);
    }

    private static function ensureTodayPath(): ?string
    {
        $dir = self::logDirectory();
        if ($dir === null) {
            return null;
        }

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);

            $htaccess = $dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                @file_put_contents($htaccess, "Deny from all\n");
            }
            $index = $dir . '/index.php';
            if (!file_exists($index)) {
                @file_put_contents($index, "<?php\n// Silence is golden.\n");
            }
        }

        return $dir . '/app-' . gmdate('Y-m-d') . '.log';
    }

    private static function logDirectory(): ?string
    {
        $upload = wp_upload_dir(null, false);
        if (!empty($upload['error']) || empty($upload['basedir'])) {
            return null;
        }
        return rtrim((string) $upload['basedir'], '/\\') . DIRECTORY_SEPARATOR . self::DIR;
    }
}
