<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcurementManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Beschaffung“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ProcurementManifest extends Manifest {
    public function code(): string {
        return 'procurement';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Beschaffung';
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
            'Procurement',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'procurement_requests',
            'purchase_order_advice_lines',
            'purchase_order_advices',
            'purchase_order_lines',
            'purchase_orders',
            'request_items',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'purchase-orders.*',
            'supplier-catalogs.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'purchase-orders.index',
                'supplier-catalogs.index',
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
