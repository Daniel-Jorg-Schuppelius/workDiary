<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Lieferanten und Lieferantenkataloge“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class SupplierManifest extends Manifest {
    public function code(): string {
        return 'supplier';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Lieferanten und Lieferantenkataloge';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Supplier',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'supplier_catalog_discount_groups',
            'supplier_catalog_imports',
            'supplier_catalog_item_price_tiers',
            'supplier_catalog_item_prices',
            'supplier_catalog_items',
            'supplier_catalog_product_groups',
            'supplier_catalog_sources',
            'supplier_credential_types',
            'supplier_credentials',
            'supplier_merge_dismissals',
            'suppliers',
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\Supplier\DeadlineScans\SupplierCredentialScan::class,
            ],
            \App\Services\Import\EntitySpec::class => [
                \App\Services\Supplier\Import\SupplierSpec::class,
            ],
        ];
    }
}
