<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationKpiSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Absence\Management;

use App\Enums\User\Permission;
use App\Enums\Vacation\VacationStatus;
use App\Models\Absence\Vacation;
use App\Models\Platform\{Organization, User};
use App\Services\Reporting\Contracts\ManagementKpiSource;
use App\Services\Reporting\Dto\ManagementKpi;
use Carbon\CarbonImmutable;

/**
 * Offene Urlaubsanträge in der Leitungssicht (Feature 184, MVP-1617): Stand
 * heute, gezählt wie die Urlaubsliste mit Status „beantragt“; nur für
 * Personen, die Urlaub genehmigen.
 */
final class VacationKpiSource implements ManagementKpiSource {
    public function group(): string {
        return 'hr';
    }

    public function order(): int {
        return 20;
    }

    public function kpis(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $viewer->isAdmin() && ! $viewer->hasEffectivePermission(Permission::VacationApprove->value)) {
            return [];
        }
        $pending = Vacation::query()->where('organization_id', $organization->id)->where('status', VacationStatus::Pending);
        $count = (clone $pending)->count();
        if ($count === 0) {
            return [];
        }
        $soon = (clone $pending)->where('start_date', '<', CarbonImmutable::today()->addDays(14)->toDateString())->count();

        return [
            new ManagementKpi(
                __('reporting.management.kpi.vacation_pending'),
                (string) $count,
                __('reporting.management.kpi.vacation_pending_hint', ['count' => $soon]),
                $soon > 0 ? 'warning' : 'neutral',
                route('duties.index', ['tab' => 'urlaub', 'vstatus' => VacationStatus::Pending->value]),
            ),
        ];
    }
}
