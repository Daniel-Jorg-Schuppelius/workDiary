<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NpsKpiSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Survey\Management;

use App\Enums\User\Permission;
use App\Models\Platform\{Organization, User};
use App\Models\Survey\SurveyAnswer;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reporting\Contracts\ManagementKpiSource;
use App\Services\Reporting\Dto\ManagementKpi;
use App\Services\Survey\SurveyService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/** NPS über alle im Zeitraum eingegangenen Antworten — dieselbe Formel wie die Umfrage-Auswertung. */
final class NpsKpiSource implements ManagementKpiSource {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function group(): string {
        return 'quality';
    }

    public function order(): int {
        return 10;
    }

    public function kpis(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $this->features->routeEnabled('surveys.index') || ! Gate::forUser($viewer)->allows(Permission::CustomerViewAny->value)) {
            return [];
        }
        $scores = SurveyAnswer::query()
            ->where('organization_id', $organization->id)
            ->whereHas('question', static fn ($q) => $q->where('type', 'nps'))
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->whereBetween('value_int', [0, 10])
            ->pluck('value_int')
            ->map(static fn ($v): int => (int) $v)
            ->all();
        $nps = SurveyService::nps($scores);
        if ($nps === null) {
            return [];
        }

        return [
            new ManagementKpi(
                __('reporting.management.kpi.nps'),
                (string) $nps,
                __('reporting.management.kpi.nps_hint', ['count' => count($scores)]),
                $nps < 0 ? 'error' : ($nps >= 30 ? 'success' : 'neutral'),
                route('surveys.index'),
            ),
        ];
    }
}
