<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PayrollManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Lohn & SV“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class PayrollManifest extends Manifest {
    public function code(): string {
        return 'payroll';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Lohn & SV';
    }

    public function licenseCode(): string {
        return 'module.lohn';
    }

    public function description(): string {
        return 'Lohnzuschläge und Lohnexport.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Payroll',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'external_wage_items',
            'wage_type_mappings',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'payroll.*',
            'admin.surcharge-rules.*',
        ];
    }
}
