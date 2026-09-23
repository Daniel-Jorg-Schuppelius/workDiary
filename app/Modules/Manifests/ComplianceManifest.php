<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Arbeitszeit-Compliance“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ComplianceManifest extends Manifest {
    public function code(): string {
        return 'compliance';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Arbeitszeit-Compliance';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Compliance',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'compliance_findings',
        ];
    }
}
