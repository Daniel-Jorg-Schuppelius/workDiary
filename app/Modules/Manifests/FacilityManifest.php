<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FacilityManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Liegenschaften“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class FacilityManifest extends Manifest {
    public function code(): string {
        return 'facility';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Liegenschaften';
    }

    public function licenseCode(): string {
        return 'module.liegenschaften';
    }

    public function description(): string {
        return 'Standorte, Gebäude, Etagen und Räume.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Facility',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'buildings',
            'cleaning_profiles',
            'floors',
            'room_requirement_templates',
            'room_requirements',
            'rooms',
            'sites',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'sites.*',
            'buildings.*',
            'floors.*',
            'rooms.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [
                'facility',
            ],
            'items' => [],
            'groups' => [],
        ];
    }
}
