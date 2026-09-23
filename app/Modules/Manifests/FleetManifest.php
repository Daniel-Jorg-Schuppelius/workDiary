<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FleetManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Fuhrpark“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class FleetManifest extends Manifest {
    public function code(): string {
        return 'fleet';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Fuhrpark';
    }

    public function licenseCode(): string {
        return 'module.fuhrpark';
    }

    public function description(): string {
        return 'Fahrzeuge, Assets, Reservierungen und Energiedaten.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Fleet',
            'Passenger',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'driver_license_checks',
            'passenger_concessions',
            'passenger_fare_tariff_rules',
            'passenger_fare_tariffs',
            'passenger_rides',
            'passenger_shift_settlements',
            'passenger_vehicle_profiles',
            'vehicle_reservations',
            'vehicles',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'passenger-rides.*',
            'passenger-masterdata.*',
            'passenger-settlements.*',
            'assets.*',
            'access-media.*',
            'vehicles.*',
            'api.vehicles.*',
            'api.legacy.vehicles.*',
            'driver-license-checks.*',
            'vehicle-reservations.*',
            'energy-logs.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Fleet,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [
                'fleet',
            ],
            'items' => [
                'passenger-rides.index',
                'passenger-masterdata.index',
                'passenger-settlements.index',
            ],
            'groups' => [],
        ];
    }
}
