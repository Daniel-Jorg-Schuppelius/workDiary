<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakePackageInstallStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake\Install;

use App\Models\Platform\{Organization, User};
use App\Services\Classification\Contracts\ProfileInstallStep;
use App\Services\Sales\Intake\IntakePackages;

/**
 * Branchenprofil-Schlüssel `intake_packages` (Feature 200, MVP-1641/1675):
 * je Zeile ein Prüfpaket der Aufnahmeakte —
 *
 * - `'gwg'` bzw. `['code' => 'gwg']`: Klassenpaket einschalten;
 * - `['code' => 'care_admission', 'enabled' => true]`: Paket aus dem Katalog
 *   `database/data/intake_packages.php` übernehmen und einschalten;
 * - `['key' => …, 'label' => …, 'fields' => […], …]`: Paket direkt aus dem
 *   Profil; `'enabled' => false` übernimmt es nur zum Einschalten im Dialog.
 *
 * Ergänzt nur: vorhandene Paketdaten und Schalter der Organisation bleiben,
 * unbekannte oder ungültige Zeilen zählen als übersprungen.
 */
final class IntakePackageInstallStep implements ProfileInstallStep {
    public function __construct(private readonly IntakePackages $packages) {}

    public function key(): string {
        return 'intake_packages';
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return array{created: int, skipped: int}
     */
    public function install(Organization $organization, array $rows, ?User $actor): array {
        $catalog = null;
        $definitions = [];
        $enable = [];
        foreach ($rows as $row) {
            if (is_string($row)) {
                $row = ['code' => $row];
            }
            if (! is_array($row)) {
                continue;
            }
            $key = (string) ($row['key'] ?? $row['code'] ?? '');
            if (isset($row['code']) && ! isset($row['fields'])) {
                $catalog ??= require database_path('data/intake_packages.php');
                $entry = is_array($catalog) ? ($catalog[$key] ?? null) : null;
                if (is_array($entry)) {
                    $row = array_merge($entry, ['key' => $key], array_diff_key($row, ['code' => true]));
                }
            }
            if (isset($row['fields'])) {
                $definitions[] = $row;
            }
            if (($row['enabled'] ?? true) !== false && $key !== '') {
                $enable[] = $key;
            }
        }

        $before = $this->packages->enabledKeys($organization);
        $added = $this->packages->addDefinitions($organization, $definitions);
        $this->packages->enable($organization, array_values(array_unique($enable)));
        $created = count(array_unique([...$added, ...array_diff($this->packages->enabledKeys($organization), $before)]));

        return ['created' => $created, 'skipped' => max(0, count($rows) - $created)];
    }
}
