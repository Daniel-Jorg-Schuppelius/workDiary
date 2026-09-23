<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Prozeduren“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ProcedureManifest extends Manifest {
    public function code(): string {
        return 'procedure';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Prozeduren';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Procedure',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'procedure_backup_proofs',
            'procedure_deviations',
            'procedure_documentations',
            'procedure_material_requirements',
            'procedure_parameter_definitions',
            'procedure_run_events',
            'procedure_runs',
            'procedure_step_defs',
            'procedure_step_runs',
            'procedure_template_versions',
            'procedure_templates',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Procedures,
        ];
    }
}
