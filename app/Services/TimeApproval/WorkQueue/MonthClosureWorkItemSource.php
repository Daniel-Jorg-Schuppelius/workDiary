<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthClosureWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\TimeApproval\WorkQueue;

use App\Enums\TimeApproval\MonthClosureStatus;
use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Models\Time\MonthClosure;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/** Eingereichte Monate anderer, die der Nutzer freigeben oder zurückweisen darf. */
final class MonthClosureWorkItemSource implements WorkItemSource {
    public function key(): string {
        return 'month_closure';
    }

    public function label(): string {
        return (string) __('work-queue.source.month_closure');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return $viewer->isAdmin()
            || $viewer->can(Permission::MonthApprove->value)
            || $viewer->can(Permission::MonthReject->value);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $submitted = MonthClosure::query()
            ->where('organization_id', $organization->id)
            ->where('status', MonthClosureStatus::Submitted)
            ->where('user_id', '!=', $viewer->id)
            ->with('user:id,name')
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get();

        foreach ($submitted as $closure) {
            if (! $gate->any(['approve', 'reject'], $closure)) {
                continue;
            }
            $period = CarbonImmutable::parse(sprintf('%04d-%02d-01', $closure->period_year, $closure->period_month))->translatedFormat('F Y');
            yield new WorkItem(
                source: $this->key(),
                key: $closure->getMorphClass() . ':' . $closure->id,
                title: (string) __('work-queue.item.month_closure', ['name' => $closure->user->name ?? '—', 'period' => $period]),
                url: route('admin.month-approval.index', ['status' => MonthClosureStatus::Submitted->value]),
                detail: $closure->days_open > 0 ? (string) __('work-queue.detail.days_open', ['days' => $closure->days_open]) : null,
            );
        }
    }
}
