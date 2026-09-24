<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Nachhaltigkeit & ESG“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class SustainabilityManifest extends Manifest {
    public function code(): string {
        return 'sustainability';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Nachhaltigkeit & ESG';
    }

    public function licenseCode(): string {
        return 'module.sustainability';
    }

    public function description(): string {
        return 'ESG-Bewertungen, Aktivitätsdaten mit CO₂e-Faktoren, Maßnahmen, Ziele und VSME-Berichtsvorbereitung.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Sustainability',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'sustainability_activity_records',
            'sustainability_assessment_items',
            'sustainability_assessments',
            'sustainability_criteria',
            'sustainability_emission_factors',
            'sustainability_factor_sets',
            'sustainability_frame_mappings',
            'sustainability_measures',
            'sustainability_report_snapshots',
            'sustainability_targets',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'sustainability.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Sustainability,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'sustainability.index',
            ],
            'groups' => [],
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\Sustainability\Demo\SustainabilityDemoBlock::class,
            ],
        ];
    }
}
