<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonnelKpiSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting\Management;

use App\Enums\User\Permission;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reporting\Contracts\ManagementKpiSource;
use App\Services\Reporting\Dto\ManagementKpi;
use App\Services\Reporting\{QualificationMatrixBuilder, SicknessReportBuilder};
use Carbon\{Carbon, CarbonImmutable};

/**
 * Personalkennzahlen der Leitungssicht (Feature 184, MVP-1617): Krankheitstage
 * im Zeitraum wie die Krankheits-Auswertung ohne Filter, abgelaufene und bald
 * ablaufende Qualifikationen wie die Qualifikationsmatrix (Stand heute) — je
 * nur mit dem Teamrecht der Auswertung.
 */
final class PersonnelKpiSource implements ManagementKpiSource {
    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly SicknessReportBuilder $sickness,
        private readonly QualificationMatrixBuilder $qualifications,
    ) {}

    public function group(): string {
        return 'hr';
    }

    public function order(): int {
        return 10;
    }

    public function kpis(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        return [...$this->sicknessKpis($viewer, $organization, $from, $to), ...$this->qualificationKpis($viewer, $organization)];
    }

    /** @return list<ManagementKpi> */
    private function sicknessKpis(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $this->features->routeEnabled('reports.sickness') || ! $this->holds($viewer, Permission::SickLeaveViewAny)) {
            return [];
        }
        $leaves = $this->sickness->leaves($from, $to, organizationId: (int) $organization->id);
        if ($leaves->isEmpty()) {
            return [];
        }
        $totals = $this->sickness->totals($this->sickness->rows($from, $to, $leaves));

        return [
            new ManagementKpi(
                __('reporting.management.kpi.sick_days'),
                (string) $totals['sick_workdays'],
                __('reporting.management.kpi.sick_days_hint', ['episodes' => $totals['episodes'], 'users' => $totals['users'], 'exhausted' => $totals['exhausted']]),
                $totals['exhausted'] > 0 ? 'warning' : 'neutral',
                route('reports.sickness', ['from' => $from->toDateString(), 'to' => $to->toDateString()]),
            ),
        ];
    }

    /** @return list<ManagementKpi> */
    private function qualificationKpis(User $viewer, Organization $organization): array {
        if (! $this->features->routeEnabled('reports.qualifications') || ! $this->holds($viewer, Permission::QualificationManage)) {
            return [];
        }
        $matrix = $this->qualifications->matrix(
            $this->qualifications->users(fn ($q) => $q->where('users.organization_id', $organization->id)),
            Carbon::today(),
        );
        if ($matrix['assignments'] === 0) {
            return [];
        }

        return [
            new ManagementKpi(
                __('reporting.management.kpi.qualifications_expired'),
                (string) $matrix['expired'],
                __('reporting.management.kpi.qualifications_expiring', ['count' => $matrix['expiring'], 'days' => QualificationMatrixBuilder::EXPIRY_WARN_DAYS]),
                match (true) {
                    $matrix['expired'] > 0 => 'error',
                    $matrix['expiring'] > 0 => 'warning',
                    default => 'success',
                },
                route('reports.qualifications'),
            ),
        ];
    }

    private function holds(User $viewer, Permission $permission): bool {
        return $viewer->isAdmin() || $viewer->hasEffectivePermission($permission->value);
    }
}
