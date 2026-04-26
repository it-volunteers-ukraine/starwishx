<?php
// File: inc/launchpad/Data/Repositories/CountriesRepository.php

declare(strict_types=1);

namespace Launchpad\Data\Repositories;

use Launchpad\Data\Migrations\CreateCountriesTable;

/**
 * Read-only access to wp_sw_countries (curated country dictionary).
 *
 * Source for opportunity form dropdowns and code-based resolution
 * (alpha-2 slug → ISO numeric id). The table is seeded once on
 * install (see CreateCountriesTable::seed) and curated subsequently
 * via bumped-VERSION migrations — there is no write surface here.
 *
 * Sort: priority ASC, name ASC. Priority drives editorial pinning
 * (Ukraine, Poland, Germany, etc.); the secondary sort keeps the long
 * tail alphabetical in the active locale's name field.
 */
class CountriesRepository
{
    private function getTable(): string
    {
        return CreateCountriesTable::tableName();
    }

    /**
     * Fetch all countries for dropdown rendering.
     *
     * Returns id (ISO 3166-1 numeric — globally meaningful, identical
     * across environments), code (alpha-2, used as URL slug elsewhere),
     * and name (Ukrainian, matching the active site locale). name_en
     * comes along so a future locale-aware UI can switch column without
     * another round trip.
     *
     * @return array<int, array{id: int, code: string, name: string, name_en: string}>
     */
    public function getAll(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT id, code, name, name_en
             FROM {$this->getTable()}
             ORDER BY priority ASC, name ASC",
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn(array $r): array => [
            'id'      => (int) $r['id'],
            'code'    => (string) $r['code'],
            'name'    => (string) $r['name'],
            'name_en' => (string) $r['name_en'],
        ], $rows);
    }

    /**
     * Fetch one country by ISO numeric id.
     *
     * @return array{id: int, code: string, name: string, name_en: string}|null
     */
    public function getById(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, code, name, name_en FROM {$this->getTable()} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return [
            'id'      => (int) $row['id'],
            'code'    => (string) $row['code'],
            'name'    => (string) $row['name'],
            'name_en' => (string) $row['name_en'],
        ];
    }

    /**
     * Fetch one country by alpha-2 code (case-insensitive).
     *
     * Reserved for slug-based URL resolution in Listing — `?country=ua`
     * resolves here to the numeric id stored in wp_opportunity_countries.
     *
     * @return array{id: int, code: string, name: string, name_en: string}|null
     */
    public function getByCode(string $code): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, code, name, name_en FROM {$this->getTable()} WHERE code = %s",
                strtolower(trim($code))
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return [
            'id'      => (int) $row['id'],
            'code'    => (string) $row['code'],
            'name'    => (string) $row['name'],
            'name_en' => (string) $row['name_en'],
        ];
    }
}
