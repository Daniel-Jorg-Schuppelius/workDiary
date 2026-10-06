<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsvAndApiTimeImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TimeTracking;

use App\Models\Platform\Organization;
use Carbon\CarbonImmutable;

/** Zeiterfassung mit CSV-Export und Abruf über die API — Vertrag der Admin-Seite {@see TimeImportAdminController}. */
interface CsvAndApiTimeImporter {
    /**
     * @param  array<string, mixed>  $config
     * @return array{created: int, skipped: int, unmatched: int, unresolved_users: int, updated: int, conflicts: int, removed: int}
     */
    public function importFromCsv(Organization $organization, string $csvContent, array $config): array;

    /**
     * @param  array<string, mixed>  $config
     * @return array{created: int, skipped: int, unmatched: int, unresolved_users?: int, updated?: int, conflicts?: int, removed?: int, error?: string}
     */
    public function importFromApi(Organization $organization, array $config, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array;
}
