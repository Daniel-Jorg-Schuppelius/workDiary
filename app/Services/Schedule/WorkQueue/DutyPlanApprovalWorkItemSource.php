<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DutyPlanApprovalWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Schedule\WorkQueue;

use App\Enums\Shift\DutyPlanStatus;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Models\Schedule\DutyPlan;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\CarbonFmt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/** Zur Genehmigung beantragte Dienstpläne (DutyPlanPolicy::approve), nicht die selbst beantragten. */
final class DutyPlanApprovalWorkItemSource implements WorkItemSource {
    private const string ROUTE = 'duty-plans.show';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'duty_plan';
    }

    public function label(): string {
        return (string) __('work-queue.source.duty_plan');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled(self::ROUTE)
            && Gate::forUser($viewer)->allows('approve', new DutyPlan(['organization_id' => (int) $viewer->organization_id]));
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $submitted = DutyPlan::query()
            ->where('organization_id', $organization->id)
            ->where('status', DutyPlanStatus::Submitted->value)
            ->where(static fn ($query) => $query->whereNull('submitted_by')->orWhere('submitted_by', '!=', $viewer->id))
            ->orderBy('from_date')
            ->get();

        foreach ($submitted as $plan) {
            if (! $gate->allows('approve', $plan)) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: $plan->getMorphClass() . ':' . $plan->id,
                title: (string) __('work-queue.item.duty_plan', ['title' => $plan->title]),
                url: route(self::ROUTE, $plan),
                detail: CarbonFmt::period($plan->from_date, $plan->to_date),
                dueAt: CarbonImmutable::instance($plan->from_date),
            );
        }
    }
}
