<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Bau & GAEB“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class GaebManifest extends Manifest {
    public function code(): string {
        return 'gaeb';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Bau & GAEB';
    }

    public function licenseCode(): string {
        return 'module.bau';
    }

    public function description(): string {
        return 'Bau-/Ausbau: GAEB-Leistungsverzeichnisse, Ordnungszahlen, Aufmaß und Nachträge.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Gaeb',
            'Construction',
            'Costing',
            'Catalog',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'bill_of_quantities',
            'boq_catalog_assignments',
            'boq_catalogs',
            'boq_change_orders',
            'boq_cost_types',
            'boq_exports',
            'boq_item_cost_approaches',
            'boq_item_mappings',
            'boq_item_price_snapshots',
            'boq_item_progress',
            'boq_item_quantity_splits',
            'boq_items',
            'boq_sections',
            'catalog_assignment_rules',
            'catalog_code_mappings',
            'catalog_entries',
            'catalog_registries',
            'construction_notices',
            'cost_element_catalogs',
            'cost_elements',
            'cost_estimate_items',
            'cost_estimates',
            'gaeb_imports',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'construction-notices.*',
            'bill-of-quantities.*',
            'catalog-rules.*',
            'cost-catalogs.*',
            'projects.hoai-report',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'construction-notices.index',
                'bill-of-quantities.index',
                'bill-of-quantities.packages',
                'catalog-rules.index',
                'cost-catalogs.index',
            ],
            'groups' => [],
        ];
    }
}
