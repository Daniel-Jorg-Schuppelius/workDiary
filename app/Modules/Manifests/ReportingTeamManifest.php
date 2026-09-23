<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportingTeamManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Team-Auswertungen“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ReportingTeamManifest extends Manifest {
    public function code(): string {
        return 'reporting_team';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Team-Auswertungen';
    }

    public function licenseCode(): string {
        return 'module.auswertungen_team';
    }

    public function description(): string {
        return 'Team- und Auswertungs-Reports.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [];
    }

    /** @return list<string> */
    public function tables(): array {
        return [];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'reports.week-by-user',
            'reports.month-by-user-team',
            'reports.coverage',
            'reports.absences',
            'reports.sickness',
            'reports.qualifications',
            'reports.customers',
            'reports.entry-types',
            'reports.assets',
            'reports.customer-project',
            'reports.project-details',
            'reports.project-inactive',
            'reports.operations',
            'reports.economics',
            'reports.arbzg-compliance',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [],
            'groups' => [
                'reports-team',
                'reports-projects',
                'reports-resources',
            ],
        ];
    }
}
