<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Geräte- & Maschinenverleih“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class RentalManifest extends Manifest {
    public function code(): string {
        return 'rental';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Geräte- & Maschinenverleih';
    }

    public function licenseCode(): string {
        return 'module.rental';
    }

    public function description(): string {
        return 'Verleihakten mit Verfügbarkeitskalender, Reservierung, Übergabe-/Rücknahmeprotokollen, Kaution und Faktura-Übergabe.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Rental',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'rental_accessory_items',
            'rental_case_assets',
            'rental_cases',
            'rental_charges',
            'rental_condition_items',
            'rental_deposits',
            'rental_handover_reports',
            'rental_profiles',
            'rental_rate_cards',
            'rental_rate_items',
            'rental_report_snapshots',
            'rental_requests',
            'rental_reservations',
            'rental_return_reports',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'rental.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Rental,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'rental.index',
                'rental.calendar',
                'rental.profiles.index',
                'rental.rates.index',
                'rental.reports.index',
                'rental.requests.index',
            ],
            'groups' => [],
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\Rental\Demo\RentalDemoBlock::class,
            ],
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\Rental\DeadlineScans\RentalReturnScan::class,
            ],
        ];
    }
}
