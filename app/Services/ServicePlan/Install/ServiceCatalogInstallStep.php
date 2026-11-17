<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceCatalogInstallStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan\Install;

use App\Models\Platform\{Organization, User};
use App\Services\Classification\Contracts\ProfileInstallStep;
use App\Services\ServicePlan\ServiceTemplates;

/**
 * Branchenprofil-Schlüssel `service_catalog` (Feature 199, MVP-1674):
 * Leistungsvorlagen des Leistungsplans. Eine Zeile nennt `entry_type` (Slug),
 * optional `product_group` (Code), `period_kind`, `mandatory` (Vorgabe Pflicht
 * oder Wahl), `label` und `label_i18n`. Ergänzt nur fehlende Vorlagen; läuft
 * nach `entry_type_rules`, damit die Eintragsarten des Profils schon da sind.
 */
final class ServiceCatalogInstallStep implements ProfileInstallStep {
    public function __construct(private readonly ServiceTemplates $templates) {}

    public function key(): string {
        return 'service_catalog';
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return array{created: int, skipped: int}
     */
    public function install(Organization $organization, array $rows, ?User $actor): array {
        return $this->templates->install($organization, $rows, $actor);
    }
}
