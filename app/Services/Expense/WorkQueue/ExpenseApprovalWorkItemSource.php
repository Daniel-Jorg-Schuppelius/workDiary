<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseApprovalWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Expense\WorkQueue;

use App\Enums\Expense\ExpenseStatus;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Models\Travel\Expense;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\CarbonFmt;
use Illuminate\Support\Facades\Gate;

/** Eingereichte Spesen anderer, über die der Nutzer entscheiden darf (ExpensePolicy::decide). */
final class ExpenseApprovalWorkItemSource implements WorkItemSource {
    private const ROUTE = 'expense-approvals.inbox';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'expense_approval';
    }

    public function label(): string {
        return (string) __('work-queue.source.expense_approval');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled(self::ROUTE) && Gate::forUser($viewer)->allows('decide', new Expense);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $pending = Expense::query()
            ->where('organization_id', $organization->id)
            ->where('status', ExpenseStatus::Pending)
            // Eigene Spesen weist der Dienst bei der Freigabe ab.
            ->where('user_id', '!=', $viewer->id)
            ->with('user:id,name')
            ->orderBy('date')
            ->get();

        foreach ($pending as $expense) {
            if (! $gate->allows('decide', $expense)) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: $expense->getMorphClass() . ':' . $expense->id,
                title: (string) __('work-queue.item.expense', ['name' => $expense->user->name ?? '—']),
                url: route(self::ROUTE),
                detail: CarbonFmt::fdate($expense->date) . ' · ' . $expense->description,
            );
        }
    }
}
