<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationApprovalWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Absence\WorkQueue;

use App\Enums\User\Permission;
use App\Enums\Vacation\VacationStatus;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Absence\Vacation;
use App\Models\Platform\{Organization, User};
use App\Services\Users\DeputyResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\CarbonFmt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Offene Urlaubsanträge, über die der Nutzer entscheiden darf (VacationPolicy::decide).
 * Eigene Anträge und Anträge, deren erste Freigabe er gab, erscheinen nicht.
 */
final class VacationApprovalWorkItemSource implements WorkItemSource {
    public function __construct(private readonly DeputyResolver $deputies) {}

    public function key(): string {
        return 'vacation_approval';
    }

    public function label(): string {
        return (string) __('work-queue.source.vacation_approval');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return $viewer->isAdmin()
            || $viewer->can(Permission::VacationApprove->value)
            || $this->deputies->actsAsDeputyForAbsentAdmin($viewer);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $pending = Vacation::query()
            ->where('organization_id', $organization->id)
            ->where('status', VacationStatus::Pending)
            ->where('user_id', '!=', $viewer->id)
            ->with('user:id,name')
            ->orderBy('start_date')
            ->get();

        foreach ($pending as $vacation) {
            if ((int) $vacation->first_approved_by === (int) $viewer->id || ! $gate->allows('decide', $vacation)) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: $vacation->getMorphClass() . ':' . $vacation->id,
                title: (string) __('work-queue.item.vacation', ['name' => $vacation->user->name ?? '—']),
                url: route('duties.index', ['tab' => 'urlaub', 'vstatus' => VacationStatus::Pending->value]),
                detail: CarbonFmt::fdate($vacation->start_date) . ' – ' . CarbonFmt::fdate($vacation->end_date),
                dueAt: CarbonImmutable::instance($vacation->start_date),
            );
        }
    }
}
