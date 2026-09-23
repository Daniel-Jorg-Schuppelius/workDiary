<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbsenceManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Urlaub und Krankheit“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class AbsenceManifest extends Manifest {
    public function code(): string {
        return 'absence';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Urlaub und Krankheit';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Absence',
            'Sickness',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'sick_leaves',
            'vacation_entitlements',
            'vacations',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Absences,
        ];
    }
}
