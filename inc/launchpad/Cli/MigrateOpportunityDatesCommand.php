<?php
// File: inc/launchpad/Cli/MigrateOpportunityDatesCommand.php

declare(strict_types=1);

namespace Launchpad\Cli;

use Launchpad\Data\Repositories\OpportunityDetailsRepository;
use WP_CLI;

/**
 * One-time backfill: normalize opportunity_date_starts/ends post_meta
 * into canonical Y-m-d and populate wp_opportunity_details.
 *
 * Why we need this: historical meta rows carry Ymd, d/m/Y, and Y-m-d
 * shapes (see OpportunitiesService::formatDateForUI's 3-format fallback).
 * Before PR#5 can switch reads to the new table, every existing row must
 * have (a) a canonical post_meta value and (b) a matching details row.
 *
 * Usage:
 *   wp starwish migrate-opportunity-dates              # dry-run (default)
 *   wp starwish migrate-opportunity-dates --apply      # write changes
 *   wp starwish migrate-opportunity-dates --apply --batch=100
 *
 * Dry-run is the default — the command must report unparseable rows
 * before anyone touches data. Live runs should be preceded by a manual
 * review of the "unparseable" list.
 */
class MigrateOpportunityDatesCommand
{
    private OpportunityDetailsRepository $repository;

    /** @var string[] Formats tried in order. Order matches existing fallback. */
    private const PARSE_FORMATS = ['Y-m-d', 'd/m/Y', 'Ymd'];

    // To trigger: update_option('starwish_run_opportunity_dates_migration', 1);
    // Fires once on the next request, then self-clears.
    private const TRIGGER_OPTION = 'starwish_run_opportunity_dates_migration';

    public function __construct(?OpportunityDetailsRepository $repository = null)
    {
        $this->repository = $repository ?? new OpportunityDetailsRepository();
    }

    /**
     * Register the command under the `starwish` top-level namespace.
     */
    public static function register(): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('starwish migrate-opportunity-dates', [self::class, 'handle']);
        }
        add_action('init', [self::class, 'maybeRunFromOptions'], 20);  // +1 line
    }
    // ── NEW: options-based entry point ────────────────────────────────────────

    public static function maybeRunFromOptions(): void
    {
        if (!get_option(self::TRIGGER_OPTION)) {
            return;
        }

        delete_option(self::TRIGGER_OPTION); // clear before running — prevents retry loops on fatal

        (new self())->applyWithoutCli();
    }

    // ── NEW: silent apply — no WP_CLI calls, no dry-run ──────────────────────

    private function applyWithoutCli(int $batchSize = 100): void
    {
        $page = 1;

        while (true) {
            $query = new \WP_Query([
                'post_type'      => 'opportunity',
                'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
                'posts_per_page' => $batchSize,
                'paged'          => $page,
                'fields'         => 'ids',
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'no_found_rows'  => false,
            ]);

            if (empty($query->posts)) {
                break;
            }

            foreach ($query->posts as $postId) {
                $postId = (int) $postId;

                $rawStart  = (string) get_post_meta($postId, 'opportunity_date_starts', true);
                $rawEnd    = (string) get_post_meta($postId, 'opportunity_date_ends',   true);
                $normStart = $this->normalize($rawStart);
                $normEnd   = $this->normalize($rawEnd);

                // Skip unparseable rows — same safety rule as the CLI path.
                if (
                    (trim($rawStart) !== '' && $normStart === '') ||
                    (trim($rawEnd)   !== '' && $normEnd   === '')
                ) {
                    continue;
                }

                if ($normStart !== '' && $rawStart !== $normStart) {
                    update_post_meta($postId, 'opportunity_date_starts', $normStart);
                }
                if ($normEnd !== '' && $rawEnd !== $normEnd) {
                    update_post_meta($postId, 'opportunity_date_ends', $normEnd);
                }

                $this->repository->upsertDates(
                    $postId,
                    $normStart === '' ? null : $normStart,
                    $normEnd   === '' ? null : $normEnd
                );
            }

            $page++;
            wp_cache_flush();
        }
    }
    
    /**
     * WP-CLI entry point.
     *
     * ## OPTIONS
     *
     * [--apply]
     * : Without this flag the command only reports what would change.
     *
     * [--batch=<n>]
     * : Number of posts to process per page. Default 100.
     *
     * ## EXAMPLES
     *
     *     wp starwish migrate-opportunity-dates
     *     wp starwish migrate-opportunity-dates --apply --batch=200
     *
     * @param array<int,string>   $args
     * @param array<string,mixed> $assoc
     */
    public static function handle(array $args, array $assoc): void
    {
        (new self())->run($assoc);
    }

    /**
     * @param array<string,mixed> $assoc
     */
    public function run(array $assoc): void
    {
        $apply     = isset($assoc['apply']);
        $batchSize = isset($assoc['batch']) ? max(1, (int) $assoc['batch']) : 100;

        $mode = $apply ? 'APPLY' : 'DRY-RUN';
        WP_CLI::log("Mode: {$mode} | batch: {$batchSize}");

        $stats = [
            'posts'          => 0,
            'normalized'     => 0,
            'already_ok'     => 0,
            'both_empty'     => 0,
            'unparseable'    => 0,
            'details_upsert' => 0,
        ];
        /** @var int[] $unparseable */
        $unparseable = [];

        $page = 1;
        while (true) {
            $query = new \WP_Query([
                'post_type'      => 'opportunity',
                'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
                'posts_per_page' => $batchSize,
                'paged'          => $page,
                'fields'         => 'ids',
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'no_found_rows'  => false,
            ]);

            if (empty($query->posts)) {
                break;
            }

            foreach ($query->posts as $postId) {
                $postId = (int) $postId;
                $stats['posts']++;

                $rawStart = (string) get_post_meta($postId, 'opportunity_date_starts', true);
                $rawEnd   = (string) get_post_meta($postId, 'opportunity_date_ends',   true);

                $normStart = $this->normalize($rawStart);
                $normEnd   = $this->normalize($rawEnd);

                // Track outcomes so the report makes sense
                $startState = $this->state($rawStart, $normStart);
                $endState   = $this->state($rawEnd,   $normEnd);

                if ($startState === 'empty' && $endState === 'empty') {
                    $stats['both_empty']++;
                } elseif ($startState === 'unparseable' || $endState === 'unparseable') {
                    $stats['unparseable']++;
                    $unparseable[] = $postId;
                    WP_CLI::warning(sprintf(
                        'post %d: unparseable (start=%s, end=%s)',
                        $postId,
                        var_export($rawStart, true),
                        var_export($rawEnd,   true)
                    ));
                    // Skip: we don't want to overwrite garbage with null
                    // silently. Operator must decide what to do.
                    continue;
                } elseif ($startState === 'already' && $endState === 'already') {
                    $stats['already_ok']++;
                } else {
                    $stats['normalized']++;
                }

                if (!$apply) {
                    continue;
                }

                // Live: normalize post_meta (no-op if already canonical)
                if ($normStart !== '' && $rawStart !== $normStart) {
                    update_post_meta($postId, 'opportunity_date_starts', $normStart);
                }
                if ($normEnd !== '' && $rawEnd !== $normEnd) {
                    update_post_meta($postId, 'opportunity_date_ends', $normEnd);
                }

                // Upsert details row — null means "unset date"
                $ok = $this->repository->upsertDates(
                    $postId,
                    $normStart === '' ? null : $normStart,
                    $normEnd   === '' ? null : $normEnd
                );
                if ($ok) {
                    $stats['details_upsert']++;
                } else {
                    WP_CLI::warning("post {$postId}: details upsert failed");
                }
            }

            $page++;

            // Flush object cache between batches — WP_Query + update_postmeta_cache
            // would otherwise balloon memory on large sites.
            wp_cache_flush();
        }

        WP_CLI::log('');
        WP_CLI::log('--- Summary ---');
        foreach ($stats as $key => $value) {
            WP_CLI::log(sprintf('  %-16s %d', $key, $value));
        }

        if (!empty($unparseable)) {
            WP_CLI::log('');
            WP_CLI::log('Unparseable post IDs (skipped):');
            WP_CLI::log('  ' . implode(', ', $unparseable));
        }

        if (!$apply) {
            WP_CLI::success('Dry-run complete. Rerun with --apply to write changes.');
        } else {
            WP_CLI::success('Backfill complete.');
        }
    }

    /**
     * Try each format in order; return canonical Y-m-d or '' when the
     * input is empty or unparseable. Callers distinguish empty-vs-unparseable
     * via state(), not this function.
     */
    private function normalize(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        foreach (self::PARSE_FORMATS as $format) {
            $dt = \DateTime::createFromFormat($format, $raw);
            // createFromFormat silently tolerates "2024-02-30" → 2024-03-01.
            // Re-format and compare to catch that drift.
            if ($dt && $dt->format($format) === $raw) {
                return $dt->format('Y-m-d');
            }
        }

        return '';
    }

    /**
     * Classify a single field outcome: empty | already | normalized | unparseable.
     */
    private function state(string $raw, string $norm): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return 'empty';
        }
        if ($norm === '') {
            return 'unparseable';
        }
        if ($norm === $trimmed) {
            return 'already';
        }
        return 'normalized';
    }
}
