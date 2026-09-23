<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Fertigung“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ManufacturingManifest extends Manifest {
    public function code(): string {
        return 'manufacturing';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Fertigung';
    }

    public function licenseCode(): string {
        return 'module.lager';
    }

    public function ownsLicense(): bool {
        return false;
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Manufacturing',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'manufacturing_order_materials',
            'manufacturing_order_reports',
            'manufacturing_orders',
            'work_centers',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'manufacturing-orders.*',
            'manufacturing-planning.*',
            'work-centers.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'manufacturing-orders.index',
                'work-centers.index',
            ],
            'groups' => [],
        ];
    }

    /** @return list<string> */
    public function requires(): array {
        return [
            'inventory',
        ];
    }
}
