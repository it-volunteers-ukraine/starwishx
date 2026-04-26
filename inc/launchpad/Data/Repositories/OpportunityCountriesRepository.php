<?php
// File: inc/launchpad/Data/Repositories/OpportunityCountriesRepository.php

declare(strict_types=1);

namespace Launchpad\Data\Repositories;

use Launchpad\Data\Migrations\CreateOpportunityCountriesTable;

/**
 * Thin data access over wp_opportunity_countries.
 *
 * Junction table for the many-to-many relationship between opportunity
 * posts and curated countries (wp_sw_countries). Composite primary key
 * (post_id, country_id) enforces uniqueness; INSERT IGNORE on assign()
 * makes calls idempotent so the backfill migration and any future
 * dual-write path can re-run without unique-key collisions.
 *
 * No FKs (dbDelta limitation), so cleanup is hooked in the service
 * layer — see OpportunitiesService::cleanupCountries on delete_post.
 */
class OpportunityCountriesRepository
{
    private function getTable(): string
    {
        return CreateOpportunityCountriesTable::tableName();
    }

    /**
     * Insert a (post, country) association if it doesn't already exist.
     *
     * INSERT IGNORE relies on the composite primary key — duplicate
     * pairs become silent no-ops rather than errors. Returns true on
     * insert OR no-op (caller doesn't need to distinguish); false only
     * when the underlying $wpdb->query reports failure.
     */
    public function assign(int $postId, int $countryId): bool
    {
        global $wpdb;

        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$this->getTable()} (post_id, country_id) VALUES (%d, %d)",
                $postId,
                $countryId
            )
        );

        return $result !== false;
    }

    /**
     * Set the (single) country for $postId, enforcing 1:1 cardinality
     * at the application layer. The composite PK on (post_id, country_id)
     * technically permits many-to-many, but the opportunity form is
     * single-select — so writes go through this method, not assign().
     *
     * Atomic delete-then-insert: drops any prior assignment, then
     * inserts the new one. Pass null (or 0) to clear without
     * reassigning. The caller is expected to be inside a transaction
     * (saveOpportunity wraps every save in one), so the empty state
     * between DELETE and INSERT is never observed by other readers.
     */
    public function setCountry(int $postId, ?int $countryId): bool
    {
        global $wpdb;

        $deleted = $wpdb->delete(
            $this->getTable(),
            ['post_id' => $postId],
            ['%d']
        );
        if ($deleted === false) {
            return false;
        }

        if ($countryId === null || $countryId === 0) {
            return true;
        }

        return $this->assign($postId, $countryId);
    }

    /**
     * Fetch the (single) country_id for $postId, or null if unset.
     *
     * 1:1 is enforced at write time via setCountry(); LIMIT 1 here is
     * defensive — picks a deterministic row if the invariant is ever
     * broken by direct SQL or migration mishap, rather than throwing.
     */
    public function getCountryId(int $postId): ?int
    {
        global $wpdb;

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT country_id FROM {$this->getTable()} WHERE post_id = %d LIMIT 1",
                $postId
            )
        );

        return $value === null ? null : (int) $value;
    }

    /**
     * Batch fetch country_ids for a page of posts, keyed by post_id.
     *
     * Missing post ids are simply absent from the result — the listing
     * card formatter falls back to an empty country display rather than
     * surfacing a synthetic placeholder.
     *
     * @param  int[] $postIds
     * @return array<int, int>
     */
    public function getBatchCountryIds(array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }

        global $wpdb;

        $postIds = array_map('intval', $postIds);
        $placeholders = implode(',', array_fill(0, count($postIds), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, country_id
                 FROM {$this->getTable()}
                 WHERE post_id IN ($placeholders)",
                ...$postIds
            ),
            ARRAY_A
        );

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['post_id']] = (int) $row['country_id'];
        }

        return $indexed;
    }

    /**
     * Clean up on post deletion. Called from the delete_post hook —
     * without FK cascade, this is what keeps the junction in sync with
     * wp_posts when an opportunity is hard-deleted.
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
     * Clean up when a country is removed from wp_sw_countries.
     *
     * Not currently wired (no admin UI for country deletion yet) — kept
     * here so the call-site exists when the admin path lands. Without
     * this, deleting a country row would orphan junction rows whose
     * country_id no longer resolves.
     */
    public function deleteByCountry(int $countryId): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->getTable(),
            ['country_id' => $countryId],
            ['%d']
        );

        return $result !== false;
    }
}
