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

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            // Keine eigenen Ordner: Der Block bucht Zeiten und liegt bei der Auswertung (Reporting).
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\Reporting\Demo\TeamReportsDemoBlock::class,
            ],
            // Personalbereich der Leitungssicht aus Krankheits- und Qualifikationsauswertung (MVP-1617).
            \App\Services\Reporting\Contracts\ManagementKpiSource::class => [
                \App\Services\Reporting\Management\PersonnelKpiSource::class,
                // Durchlaufzeit je Auftragsart aus der Auftragstypanalyse (Feature 179, MVP-1670).
                \App\Services\Reporting\Management\LeadTimeKpiSource::class,
            ],
        ];
    }
}
