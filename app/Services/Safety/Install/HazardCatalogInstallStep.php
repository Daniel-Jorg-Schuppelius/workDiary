<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HazardCatalogInstallStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Safety\Install;

use App\Models\Platform\{Organization, User};
use App\Models\Safety\HazardCatalogItem;
use App\Services\Classification\Contracts\ProfileInstallStep;

/**
 * Branchenprofil-Schlüssel `hazard_catalog` (MVP-1002): typische Gefährdungen
 * der Branche als Katalog. Codes lauten `profil/schlüssel` — der Installer reicht
 * den Profilcode nicht an die Schritte weiter. Ergänzt nur fehlende Codes;
 * eigene Änderungen der Organisation bleiben unberührt.
 */
class HazardCatalogInstallStep implements ProfileInstallStep {
    public function key(): string {
        return 'hazard_catalog';
    }

    /**
     * @param  array<int|string, mixed>  $rows  Liste aus code, category, hazard, measure, severity, likelihood
     * @return array{created: int, skipped: int}
     */
    public function install(Organization $organization, array $rows, ?User $actor): array {
        $created = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $code = is_array($row) ? (string) ($row['code'] ?? '') : '';
            $severity = is_array($row) ? (int) ($row['severity'] ?? 0) : 0;
            $likelihood = is_array($row) ? (int) ($row['likelihood'] ?? 0) : 0;
            if ($code === '' || trim((string) ($row['hazard'] ?? '')) === '' || $severity < 1 || $severity > 5 || $likelihood < 1 || $likelihood > 5) {
                $skipped++;

                continue;
            }
            $item = HazardCatalogItem::query()->withoutGlobalScopes()->firstOrCreate(
                ['organization_id' => $organization->id, 'code' => $code],
                [
                    'category' => mb_substr((string) ($row['category'] ?? ''), 0, 120),
                    'hazard' => mb_substr((string) $row['hazard'], 0, 255),
                    'measure' => $row['measure'] ?? null,
                    'severity' => $severity,
                    'likelihood' => $likelihood,
                    'source_profile' => str_contains($code, '/') ? mb_substr(strstr($code, '/', true) ?: '', 0, 64) : null,
                    'is_active' => true,
                    'created_by' => $actor?->id,
                ],
            );
            $item->wasRecentlyCreated ? $created++ : $skipped++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
