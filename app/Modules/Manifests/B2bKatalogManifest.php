<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : B2bKatalogManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „B2B-Katalogzugang (OCI-Punchout)“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class B2bKatalogManifest extends Manifest {
    public function code(): string {
        return 'b2b_katalog';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'B2B-Katalogzugang (OCI-Punchout)';
    }

    public function licenseCode(): string {
        return 'module.b2b_katalog';
    }

    public function description(): string {
        return 'Punchout-Katalog für Einkaufssysteme der B2B-Kunden (OCI 4.0) mit kundenindividuellen Freigaben/Preisen und openTRANS-2.1-Auftragseingang.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'B2b',
            'B2bCatalog',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'b2b_catalog_accesses',
            'b2b_catalog_items',
            'b2b_orders',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'b2b-catalog.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'b2b-catalog.index',
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
