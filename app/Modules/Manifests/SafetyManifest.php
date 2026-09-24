<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Arbeitsschutz“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class SafetyManifest extends Manifest {
    public function code(): string {
        return 'safety';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Arbeitsschutz';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Safety',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'hazard_assessment_items',
            'hazard_assessments',
            'medical_checkups',
            'safety_events',
            'safety_instruction_participants',
            'safety_instructions',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Safety,
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\Safety\DeadlineScans\SafetyDeadlineScans::class,
            ],
        ];
    }
}
