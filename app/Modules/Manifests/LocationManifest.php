<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Standorterfassung“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class LocationManifest extends Manifest {
    public function code(): string {
        return 'location';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Standorterfassung';
    }

    public function licenseCode(): string {
        return 'module.standorterfassung';
    }

    public function description(): string {
        return 'Standortbasierte Zeiterfassung über Geofences (OwnTracks/Traccar).';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Location',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'location_device_tokens',
            'location_pending_entries',
            'location_points',
            'location_visits',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'location.*',
            'geofences.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [
                'location',
            ],
            'items' => [],
            'groups' => [],
        ];
    }
}
