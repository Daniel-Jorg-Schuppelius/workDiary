<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResellingManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Reselling-Register“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ResellingManifest extends Manifest {
    public function code(): string {
        return 'reselling';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Reselling-Register';
    }

    public function licenseCode(): string {
        return 'module.reselling';
    }

    public function description(): string {
        return 'Weiterverkaufte Abos (Lizenzen, Domains, Hosting) mit Haltern, Abrechnungsperioden und Rechnungsbezügen.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Reselling',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'resale_imports',
            'resale_period_links',
            'resale_periods',
            'resale_price_catalog',
            'resale_purchase_entries',
            'resale_subscriptions',
            'reselling_company_mappings',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'finance.resale.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'finance.resale.index',
            ],
            'groups' => [],
        ];
    }
}
