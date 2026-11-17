<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan\Cockpit;

use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\ServicePlan\CustomerServiceWaiver;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Delegation\ActsForResolver;
use App\Services\ServicePlan\Dto\{ServicePlanCell, ServicePlanQuery};
use App\Services\ServicePlan\ServicePlanBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Leistungsplan am Kunden-Cockpit (Feature 199): je Leistung des laufenden
 * Jahres (und der Jahresleistungen des Vorjahres) die Zellen bis heute mit
 * Stand — offene zuerst — und der Sprung in Jahres- und Periodensicht des
 * Kunden. Ohne Leserecht des Plans nur die eigenen Zellen; ohne Leistungsumfang
 * kein Panel.
 */
final class ServicePlanCockpitPanel implements CockpitPanel {
    private const int LIMIT = 8;

    public function __construct(
        private readonly ServicePlanBuilder $builder,
        private readonly ActsForResolver $actsFor,
    ) {}

    public function order(): int {
        return 26;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Customer;
    }

    public function render(Model $subject, User $viewer): ?View {
        $gate = Gate::forUser($viewer);
        if (! $subject instanceof Customer || ! $gate->allows('viewAny', CustomerServiceWaiver::class)) {
            return null;
        }
        $organization = Organization::query()->find($subject->organization_id);
        if (! $organization instanceof Organization || (int) $viewer->organization_id !== (int) $organization->id) {
            return null;
        }
        $today = CarbonImmutable::today();
        $visibleTo = $gate->allows('viewAll', CustomerServiceWaiver::class) ? null : $this->actsFor->assigneeIds($viewer);
        $cells = [];
        $labels = [];
        foreach ([[ServicePlanQuery::VIEW_YEAR, $today->year - 1], [ServicePlanQuery::VIEW_YEAR, $today->year], [ServicePlanQuery::VIEW_PERIODS, $today->year]] as [$view, $year]) {
            $result = $this->builder->build($organization, new ServicePlanQuery($view, $year, $today, visibleToIds: $visibleTo, customerIds: [(int) $subject->id]));
            foreach ($result->columns as $column) {
                $labels[$column->service->key()] = $column->label;
            }
            foreach ($result->rows as $row) {
                foreach ($row->cells as $cell) {
                    if ($cell->counts() && ! $cell->period->start()->greaterThan($today)) {
                        $cells[] = $cell;
                    }
                }
            }
        }
        if ($cells === []) {
            return null;
        }
        usort($cells, static fn (ServicePlanCell $a, ServicePlanCell $b): int => [$a->state->isPending() ? 0 : 1, $a->overdue ? 0 : 1, -$a->period->start()->getTimestamp()]
            <=> [$b->state->isPending() ? 0 : 1, $b->overdue ? 0 : 1, -$b->period->start()->getTimestamp()]);

        return view('service-plan._cockpit_panel', [
            'customer' => $subject,
            'cells' => array_slice($cells, 0, self::LIMIT),
            'labels' => $labels,
            'pending' => count(array_filter($cells, static fn (ServicePlanCell $cell): bool => $cell->state->isPending())),
        ]);
    }
}
