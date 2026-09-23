<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Notfall- & Krisenmanagement“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class CrisisManifest extends Manifest {
    public function code(): string {
        return 'crisis';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Notfall- & Krisenmanagement';
    }

    public function licenseCode(): string {
        return 'module.crisis_management';
    }

    public function description(): string {
        return 'Krisenakten mit Lagebild, Krisenstab, Alarmierung, Maßnahmen, Kommunikation und Übungen.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Crisis',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'crisis_actions',
            'crisis_case_links',
            'crisis_cases',
            'crisis_communications',
            'crisis_continuity_impacts',
            'crisis_deadline_templates',
            'crisis_decisions',
            'crisis_exercises',
            'crisis_reviews',
            'crisis_roles',
            'crisis_situation_reports',
            'crisis_team_assignments',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'crisis.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Crisis,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'crisis.index',
                'crisis.exercises.index',
            ],
            'groups' => [],
        ];
    }
}
