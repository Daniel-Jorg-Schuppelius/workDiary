<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeShortfallKpiSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Project\Management;

use App\Enums\User\Permission;
use App\Models\Platform\{Organization, User};
use App\Models\Project\ProjectTimeShortfall;
use App\Services\Reporting\Contracts\ManagementKpiSource;
use App\Services\Reporting\Dto\ManagementKpi;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/** Kennzahl der Leitungssicht (Feature 194, MVP-1469): Personen-Tage unter Projekt-Soll im Zeitraum. */
final class ProjectTimeShortfallKpiSource implements ManagementKpiSource {
    public function group(): string {
        return 'operations';
    }

    public function order(): int {
        return 30;
    }

    public function kpis(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $viewer->isAdmin() && ! Gate::forUser($viewer)->allows(Permission::ReportPresenceOrganization->value)) {
            return [];
        }
        $base = ProjectTimeShortfall::query()
            ->where('organization_id', $organization->id)
            ->whereBetween('date', DateRange::days($from, $to));
        $unresolved = (clone $base)->unresolved()->count();
        $total = (clone $base)->count();
        if ($total === 0) {
            return [];
        }

        return [
            new ManagementKpi(
                __('reporting.management.kpi.project_target'),
                (string) $unresolved,
                __('reporting.management.kpi.project_target_hint', ['total' => $total]),
                $unresolved > 0 ? 'warning' : 'success',
                route('reports.plan-ist.project-persons', ['from' => $from->toDateString(), 'to' => $to->toDateString()]),
            ),
        ];
    }
}
