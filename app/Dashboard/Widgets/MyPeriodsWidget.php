<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MyPeriodsWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Models\ServicePlan\CustomerServiceWaiver;
use App\Services\Delegation\ActsForResolver;
use App\Services\ServicePlan\Dto\ServicePlanCell;
use App\Services\ServicePlan\{ServicePlanAssignments, ServicePlanCatalog};
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Karte „Meine Perioden“ (Feature 199, MVP-1639): offene Zellen des
 * Leistungsplans, für die die Person zuständig ist — auch für Personen, die
 * sie heute vertritt (MVP-1648) —, überfällige zuerst. Nur für Personen mit
 * Zugang zum Plan, die bei mindestens einem Kunden zuständig sind.
 */
class MyPeriodsWidget extends Widget {
    private const int LIMIT = 6;

    public function __construct(
        private readonly ServicePlanAssignments $assignments,
        private readonly ServicePlanCatalog $catalog,
        private readonly ActsForResolver $actsFor,
    ) {}

    public function key(): string {
        return 'my-periods';
    }

    public function label(): string {
        return (string) __('service_plan.widget.title');
    }

    public function icon(): string {
        return 'view_week';
    }

    public function defaultOrder(): int {
        return 74;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Tasks;
    }

    public function description(): ?string {
        return (string) __('service_plan.widget.description');
    }

    public function availableFor(User $user): bool {
        if (! parent::availableFor($user) || ! Gate::forUser($user)->allows('viewAny', CustomerServiceWaiver::class)) {
            return false;
        }
        $organizationId = \App\Support\OrganizationContext::currentId() ?? (int) $user->organization_id;

        return $this->assignments->customerIds($organizationId, $this->actsFor->assigneeIds($user)) !== [];
    }

    public function render(User $user): View|string {
        $organizationId = \App\Support\OrganizationContext::currentId() ?? (int) $user->organization_id;
        $organization = Organization::query()->find($organizationId);
        $cells = $organization instanceof Organization
            ? $this->assignments->pendingCells($organization, $this->actsFor->assigneeIds($user), CarbonImmutable::today())
            : [];
        $shown = array_slice($cells, 0, self::LIMIT);
        $customers = Customer::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->whereIn('id', array_values(array_unique(array_map(static fn (ServicePlanCell $cell): int => $cell->customerId, $shown))))
            ->get(['id', 'name'])
            ->keyBy('id');

        return view('dashboard.widgets.my-periods', [
            'total' => count($cells),
            'overdue' => count(array_filter($cells, static fn (ServicePlanCell $cell): bool => $cell->overdue)),
            'cells' => $shown,
            'customers' => $customers,
            'labels' => array_map(fn (ServicePlanCell $cell): string => $this->catalog->label($organizationId, $cell->service), $shown),
        ]);
    }
}
