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
