<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Lager & Artikel“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class InventoryManifest extends Manifest {
    public function code(): string {
        return 'inventory';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Lager & Artikel';
    }

    public function licenseCode(): string {
        return 'module.lager';
    }

    public function description(): string {
        return 'Lagerwirtschaft, Artikelstamm und Fertigung.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Inventory',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'inventory_outbox',
            'stock_count_lines',
            'stock_counts',
            'stock_deliveries',
            'stock_level_settings',
            'stock_lots',
            'stock_movements',
            'stock_reservations',
            'stock_serials',
            'stock_valuation_layers',
            'stock_valuations',
            'warehouse_bins',
            'warehouses',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'articles.*',
            'api.articles.*',
            'api.legacy.articles.*',
            'api.inventory.*',
            'api.legacy.inventory.*',
            'api.purchase-orders.*',
            'api.legacy.purchase-orders.*',
            'warehouses.*',
            'inventory.*',
            'serials.*',
            'supplier-scorecards.*',
            'recipe-menus.*',
            'print-orders.*',
            'pricing-margin-rules.*',
            'oci-carts.*',
            'admin.jtl.*',
            'admin.billbee.*',
            'admin.etsy.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'print-orders.index',
                'articles.index',
                'warehouses.index',
                'serials.index',
                'pricing-margin-rules.index',
                'inventory.scan',
                'inventory.lots',
                'inventory.label-templates.index',
            ],
            'groups' => [],
        ];
    }

    /** @return list<string> */
    public function plugins(): array {
        return [
            'jtl_wawi',
            'billbee',
            'etsy',
        ];
    }

    /** @return array<class-string, class-string> */
    public function bindings(): array {
        return [
            \App\Services\Claims\Contracts\RmaStockHandler::class => \App\Services\Inventory\Claims\InventoryRmaStockHandler::class,
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Plugins\Support\Contracts\PluginCapabilitySource::class => [
                \App\Services\Inventory\InventoryCapabilitySource::class,
            ],
        ];
    }
}
