<?php
// File: inc/launchpad/Data/Migrations/BackfillOpportunityCountries.php

declare(strict_types=1);

namespace Launchpad\Data\Migrations;

use Launchpad\Data\Repositories\OpportunityCountriesRepository;

/**
 * One-shot data backfill: copy existing `country` taxonomy assignments
 * into wp_opportunity_countries so the new junction is populated before
 * the read/write paths are switched off the taxonomy.
 *
 * Strategy
 * --------
 * 1. Build a slug-resolution map from wp_sw_countries (loaded once).
 * 2. Iterate all `country` terms.
 * 3. For each term, resolve its slug to a country_id via four ordered
 *    fallbacks: alpha-2 code → alpha-3 code → sanitize_title(name_en)
 *    → sanitize_title(name). The fallbacks cover the realistic slugify
 *    outcomes for either ISO-style slugs (`ua`, `ukr`) or natural-name
 *    slugs (`ukraine`, `ukraina`).
 * 4. For every opportunity tied to that term, INSERT IGNORE into the
 *    junction. Composite PK + IGNORE makes the operation idempotent.
 *
 * Idempotency / failure mode
 * --------------------------
 * On unmatched terms we LOG and SKIP, then mark the migration as run.
 * Marking-as-run is intentional: leaving it pending would cause the
 * lookup to retry on every admin_init forever, even when the data
 * problem is permanent. Rerun on demand by clearing the version
 * option:
 *
 *     DELETE FROM wp_options
 *     WHERE option_name = 'launchpad_opportunity_countries_backfill_version';
 *
 * Scope discipline
 * ----------------
 * The taxonomy assignments are NOT removed here. The taxonomy stays
 * the read source until the controller/service/UI path is switched
 * over — that's a separate cutover step. This migration is dual-write
 * preparation, not the cutover itself.
 */
class BackfillOpportunityCountries
{
    private const VERSION    = '1.0.0';
    private const OPTION_KEY = 'launchpad_opportunity_countries_backfill_version';
    private const ERROR_KEY  = 'launchpad_opportunity_countries_backfill_error';
    private const INFO_KEY   = 'launchpad_opportunity_countries_backfill_info';

    public static function needsUpgrade(): bool
    {
        return get_option(self::OPTION_KEY) !== self::VERSION;
    }

    public static function run(): void
    {
        // No taxonomy registered → nothing to backfill. Mark complete so
        // we don't keep checking. Future installs that add the taxonomy
        // can clear the option and re-run manually.
        if (!taxonomy_exists('country')) {
            update_option(self::OPTION_KEY, self::VERSION);
            return;
        }

        $maps = self::buildLookupMaps();
        if (empty($maps['code2'])) {
            // sw_countries is empty (seed didn't run, or table missing).
            // Without a target to map into, the backfill is a no-op —
            // but we DON'T mark complete, so it retries once the seed
            // is populated.
            update_option(self::ERROR_KEY, 'sw_countries lookup empty; backfill deferred');
            return;
        }

        $terms = get_terms([
            'taxonomy'   => 'country',
            'hide_empty' => false,
        ]);
        if (is_wp_error($terms) || empty($terms)) {
            update_option(self::OPTION_KEY, self::VERSION);
            delete_option(self::ERROR_KEY);
            return;
        }

        $repo        = new OpportunityCountriesRepository();
        $unmatched   = [];
        $insertCount = 0;
        $postCount   = 0;

        foreach ($terms as $term) {
            $countryId = self::resolveCountryId($term, $maps);
            if ($countryId === null) {
                $unmatched[] = sprintf('%s (term_id=%d)', $term->slug, $term->term_id);
                continue;
            }

            $postIds = get_objects_in_term($term->term_id, 'country');
            if (is_wp_error($postIds) || empty($postIds)) {
                continue;
            }

            foreach ($postIds as $postId) {
                $postId = (int) $postId;

                // Restrict to opportunity post type — the taxonomy is
                // only attached to that CPT (per the ACF config), but
                // a defensive filter costs nothing.
                $post = get_post($postId);
                if (!$post || $post->post_type !== 'opportunity') {
                    continue;
                }

                if ($repo->assign($postId, $countryId)) {
                    $insertCount++;
                }
                $postCount++;
            }
        }

        if (!empty($unmatched)) {
            $message = 'Unmatched country term slugs: ' . implode(', ', $unmatched);
            error_log('Launchpad Backfill: ' . $message);
            update_option(self::ERROR_KEY, $message);
        } else {
            delete_option(self::ERROR_KEY);
        }

        update_option(self::INFO_KEY, sprintf(
            'Processed %d term assignment(s); inserted %d new junction row(s).',
            $postCount,
            $insertCount
        ));
        update_option(self::OPTION_KEY, self::VERSION);
    }

    /**
     * Build four lookup maps from wp_sw_countries:
     *   - code2:   alpha-2 → id
     *   - code3:   alpha-3 → id
     *   - slug_en: sanitize_title(name_en) → id
     *   - slug_uk: sanitize_title(name)    → id
     *
     * Loaded once per migration run. ~184 rows × 5 fields is small
     * enough that PHP-side hashing beats per-term SQL queries.
     *
     * @return array{code2: array<string,int>, code3: array<string,int>, slug_en: array<string,int>, slug_uk: array<string,int>}
     */
    private static function buildLookupMaps(): array
    {
        global $wpdb;
        $table = CreateCountriesTable::tableName();

        $rows = $wpdb->get_results("SELECT id, code, code3, name, name_en FROM $table");
        $maps = ['code2' => [], 'code3' => [], 'slug_en' => [], 'slug_uk' => []];

        if (!is_array($rows)) {
            return $maps;
        }

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $maps['code2'][strtolower((string) $row->code)]    = $id;
            $maps['code3'][strtolower((string) $row->code3)]   = $id;
            $maps['slug_en'][sanitize_title((string) $row->name_en)] = $id;
            $maps['slug_uk'][sanitize_title((string) $row->name)]    = $id;
        }

        return $maps;
    }

    /**
     * Resolve a taxonomy term to a country_id using the slug first
     * (because that's what's stable across edits) and the term name as
     * a fallback (handy if an admin renamed the term but kept the slug
     * as some unrelated string).
     */
    private static function resolveCountryId(\WP_Term $term, array $maps): ?int
    {
        $slug = strtolower((string) $term->slug);

        if (isset($maps['code2'][$slug]))   return $maps['code2'][$slug];
        if (isset($maps['code3'][$slug]))   return $maps['code3'][$slug];
        if (isset($maps['slug_en'][$slug])) return $maps['slug_en'][$slug];
        if (isset($maps['slug_uk'][$slug])) return $maps['slug_uk'][$slug];

        // Fallback: try the human-readable term name as a slugified key.
        $nameKey = sanitize_title((string) $term->name);
        if (isset($maps['slug_en'][$nameKey])) return $maps['slug_en'][$nameKey];
        if (isset($maps['slug_uk'][$nameKey])) return $maps['slug_uk'][$nameKey];

        return null;
    }
}
