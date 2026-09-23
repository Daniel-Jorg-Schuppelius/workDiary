<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduleManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Planung“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ScheduleManifest extends Manifest {
    public function code(): string {
        return 'schedule';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Planung';
    }

    public function licenseCode(): string {
        return 'module.planung';
    }

    public function description(): string {
        return 'Dienst-/Schichtplanung, Stundenzettel, Touren und Disposition.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Schedule',
            'Dispatch',
            'Routing',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'coverage_requirements',
            'desired_shifts',
            'duty_plans',
            'scheduled_shifts',
            'shift_exchanges',
            'shift_rotation_assignments',
            'shift_rotation_entries',
            'shift_rotations',
            'shift_type_qualifications',
            'shift_types',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'duty-plans.*',
            'schedule.*',
            'shift-types.*',
            'timesheets.*',
            'flex.*',
            'tours.*',
            'dispatch.*',
            'patrols.*',
            'appointments.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Scheduling,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [
                'plan',
            ],
            'items' => [],
            'groups' => [],
        ];
    }
}
