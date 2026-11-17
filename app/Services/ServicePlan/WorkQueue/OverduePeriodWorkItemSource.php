<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OverduePeriodWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan\WorkQueue;

use App\Enums\Period\PeriodKind;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Models\ServicePlan\CustomerServiceWaiver;
use App\Services\Delegation\Contracts\DelegableWork;
use App\Services\ServicePlan\Dto\ServicePlanCell;
use App\Services\ServicePlan\{ServicePlanAssignments, ServicePlanCatalog};
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * „Überfällige Perioden“ im Arbeitsvorrat (Feature 199, MVP-1639): Pflicht-
 * leistungen ohne Auftrag, deren Fälligkeit (Kundenfrist, unter dem Jahr sonst
 * das Periodenende) überschritten ist, für die zuständige Person der
 * Fristmerkmale. Überfällige Aufträge stehen schon unter „Aufträge“.
 * Zugleich delegierbare Arbeit (Feature 202, MVP-1648): die Vertretung sieht
 * sie, gekennzeichnet „in Vertretung für …“.
 */
final class OverduePeriodWorkItemSource implements DelegableWork, WorkItemSource {
    public function __construct(
        private readonly ServicePlanAssignments $assignments,
        private readonly ServicePlanCatalog $catalog,
    ) {}

    public function key(): string {
        return 'service_plan_period';
    }

    public function label(): string {
        return (string) __('work-queue.source.service_plan_period');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Reminder;
    }

    public function available(User $viewer): bool {
        return Gate::forUser($viewer)->allows('viewAny', CustomerServiceWaiver::class);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $cells = $this->overdue($viewer, $organization);
        if ($cells === []) {
            return;
        }
        $names = Customer::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->whereIn('id', array_values(array_unique(array_map(static fn (ServicePlanCell $cell): int => $cell->customerId, $cells))))
            ->get(['id', 'name'])
            ->keyBy('id');

        foreach ($cells as $cell) {
            $customer = $names->get($cell->customerId);
            yield new WorkItem(
                source: $this->key(),
                key: 'service_plan:' . $cell->customerId . ':' . $cell->service->key() . ':' . $cell->period->key(),
                title: (string) __('work-queue.item.service_plan_period', [
                    'service' => $this->catalog->label((int) $organization->id, $cell->service),
                    'period' => $cell->period->label(),
                ]),
                url: route($cell->period->kind === PeriodKind::Year ? 'service-plan.index' : 'service-plan.periods.index', [
                    'customer' => $customer?->sqid,
                    'year' => $cell->period->year,
                    'state' => 'missing',
                ]),
                detail: $customer?->name,
                dueAt: $cell->due,
                personal: true,
            );
        }
    }

    public function count(User $user): int {
        $organization = $user->organization;

        return $organization instanceof Organization ? count($this->overdue($user, $organization)) : 0;
    }

    /** @return list<ServicePlanCell> fehlende, überfällige Zellen, für die die Person zuständig ist */
    private function overdue(User $user, Organization $organization): array {
        return array_values(array_filter(
            $this->assignments->pendingCells($organization, [(int) $user->id], CarbonImmutable::today()),
            static fn (ServicePlanCell $cell): bool => $cell->overdue && $cell->orderId === null,
        ));
    }
}
