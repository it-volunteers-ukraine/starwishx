<?php
// File: inc/launchpad/Data/Repositories/OpportunityDetailsRepository.php

declare(strict_types=1);

namespace Launchpad\Data\Repositories;

use Launchpad\Data\Migrations\CreateOpportunityDetailsTable;

/**
 * Thin data access over wp_opportunity_details.
 *
 * The table stores one row per opportunity post with typed date columns.
 * Callers pass dates as Y-m-d strings — MySQL coerces them into DATE
 * values and rejects malformed input, giving us a hard integrity floor
 * even if application validation is bypassed.
 *
 * `is_expired` is computed in PHP on hydration (see computeIsExpired)
 * rather than stored, so the rule follows WP's configured site timezone
 * rather than MySQL's server timezone.
 *
 * Nulls are allowed for both dates; pass null or empty string to clear.
 */
class OpportunityDetailsRepository
{
    private function getTable(): string
    {
        return CreateOpportunityDetailsTable::tableName();
    }

    /**
     * Insert or update the row for $postId.
     *
     * Uses $wpdb->replace rather than separate insert/update to keep the
     * write path branch-free. Dates are normalized: empty string → null,
     * so the CASE in the generated column treats "unset" correctly.
     */
    public function upsertDates(int $postId, ?string $dateStart, ?string $dateEnd): bool
    {
        global $wpdb;

        $dateStart = ($dateStart === '' || $dateStart === null) ? null : $dateStart;
        $dateEnd   = ($dateEnd   === '' || $dateEnd   === null) ? null : $dateEnd;

        $data   = ['post_id' => $postId, 'date_start' => $dateStart, 'date_end' => $dateEnd];
        $format = ['%d', '%s', '%s'];

        $result = $wpdb->replace($this->getTable(), $data, $format);

        return $result !== false;
    }

    /**
     * Fetch one row keyed by post_id.
     *
     * Returns null when no row exists — callers should fall back to
     * post_meta during the dual-write / backfill window.
     *
     * @return array{date_start: ?string, date_end: ?string, is_expired: int}|null
     */
    public function getDates(int $postId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT date_start, date_end
                 FROM {$this->getTable()}
                 WHERE post_id = %d",
                $postId
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return [
            'date_start' => $row['date_start'],
            'date_end'   => $row['date_end'],
            'is_expired' => $this->computeIsExpired($row['date_end']),
        ];
    }

    /**
     * Batch variant for list views — one query for an entire page of cards.
     *
     * Indexed by post_id so callers can array_key access per-card. Missing
     * post_ids are simply absent from the result; callers decide fallback
     * behavior (usually: read post_meta for that id during migration window).
     *
     * @param int[] $postIds
     * @return array<int, array{date_start: ?string, date_end: ?string, is_expired: int}>
     */
    public function getBatchDates(array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }

        global $wpdb;

        $postIds = array_map('intval', $postIds);
        $placeholders = implode(',', array_fill(0, count($postIds), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, date_start, date_end
                 FROM {$this->getTable()}
                 WHERE post_id IN ($placeholders)",
                ...$postIds
            ),
            ARRAY_A
        );

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['post_id']] = [
                'date_start' => $row['date_start'],
                'date_end'   => $row['date_end'],
                'is_expired' => $this->computeIsExpired($row['date_end']),
            ];
        }

        return $indexed;
    }

    /**
     * Clean up on post deletion. Called from the delete_post hook — without
     * FK cascade (dbDelta can't declare FKs reliably), this is the only
     * thing keeping the table in sync with wp_posts.
     */
    public function deleteByPost(int $postId): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->getTable(),
            ['post_id' => $postId],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Derive expired flag in PHP using WP's site timezone. Not a MySQL
     * generated column because CURDATE/NOW are disallowed in generated
     * expressions, and a DB-side rule would follow MySQL server TZ rather
     * than the site's.
     */
    private function computeIsExpired(?string $dateEnd): int
    {
        if ($dateEnd === null || $dateEnd === '') {
            return 0;
        }
        return $dateEnd < current_time('Y-m-d') ? 1 : 0;
    }
}
