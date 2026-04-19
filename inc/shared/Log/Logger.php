<?php

/**
 * Shared\Log\Logger — static facade over Monolog 3.x.
 *
 * Writes to wp-content/uploads/sw/app-YYYY-MM-DD.log via Monolog's
 * RotatingFileHandler, which auto-prunes files older than RETENTION_DAYS.
 *
 * Monolog 3 is PSR-3 compatible; this facade keeps a simpler static call
 * shape (`Logger::info($module, $message, $context)`) so call sites don't
 * have to juggle channel construction — $module becomes the Monolog
 * channel, memoized per name.
 *
 * File: inc/shared/Log/Logger.php
 */

declare(strict_types=1);

namespace Shared\Log;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;

final class Logger
{
    private const DIR            = 'sw';
    private const FILENAME       = 'app.log';
    private const RETENTION_DAYS = 30;

    /** @var array<string, MonologLogger> */
    private static array $channels = [];

    public static function info(string $module, string $message, array $context = []): void
    {
        self::channel($module)->info($message, $context);
    }

    public static function warning(string $module, string $message, array $context = []): void
    {
        self::channel($module)->warning($message, $context);
    }

    public static function error(string $module, string $message, array $context = []): void
    {
        self::channel($module)->error($message, $context);
    }

    public static function debug(string $module, string $message, array $context = []): void
    {
        self::channel($module)->debug($message, $context);
    }

    /**
     * Reset the memoized channel registry — intended for tests.
     */
    public static function reset(): void
    {
        self::$channels = [];
    }

    private static function channel(string $module): MonologLogger
    {
        $name = $module !== '' ? $module : 'app';

        if (isset(self::$channels[$name])) {
            return self::$channels[$name];
        }

        $logger = new MonologLogger($name);

        $path = self::ensureLogPath();
        if ($path !== null) {
            $minLevel = (defined('WP_DEBUG') && WP_DEBUG) ? Level::Debug : Level::Info;

            $handler = new RotatingFileHandler(
                $path,
                self::RETENTION_DAYS,
                $minLevel,
                true,   // bubble
                null,   // filePermission — inherit umask
                true    // useLocking — safer under concurrent PHP-FPM writers on Windows
            );

            // Default Monolog format: "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
            // Example: [2026-04-19T14:23:11+00:00] Users.INFO: Wiped inactive account {"userId":42}
            $handler->setFormatter(new LineFormatter(
                null,   // use default template
                'c',    // ISO-8601 datetime
                true,   // allowInlineLineBreaks — useful for stack traces
                true    // ignoreEmptyContextAndExtra
            ));

            $logger->pushHandler($handler);
        }

        self::$channels[$name] = $logger;
        return $logger;
    }

    private static function ensureLogPath(): ?string
    {
        $upload = wp_upload_dir(null, false);
        if (!empty($upload['error']) || empty($upload['basedir'])) {
            return null;
        }

        $dir = rtrim((string) $upload['basedir'], '/\\') . DIRECTORY_SEPARATOR . self::DIR;

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);

            $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
            if (!file_exists($htaccess)) {
                @file_put_contents($htaccess, "Deny from all\n");
            }
            $index = $dir . DIRECTORY_SEPARATOR . 'index.php';
            if (!file_exists($index)) {
                @file_put_contents($index, "<?php\n// Silence is golden.\n");
            }
        }

        return $dir . DIRECTORY_SEPARATOR . self::FILENAME;
    }
}
