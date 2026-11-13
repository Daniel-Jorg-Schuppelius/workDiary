<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayCorrectionWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\TimeApproval\WorkQueue;

use App\Enums\TimeApproval\{DayClosureStatus, DayCorrectionStatus};
use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Diary\{DayClosure, DayCorrectionRequest};
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Users\TeamScope;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\{CarbonFmt, Sqid};
use Illuminate\Support\Facades\Gate;

/**
 * Offene Korrekturanträge zu Tagesabschlüssen anderer, die der Nutzer
 * entscheiden darf (DayClosurePolicy::approveCorrection) und deren Tag er
 * öffnen kann — dort liegen die Knöpfe.
 */
final class DayCorrectionWorkItemSource implements WorkItemSource {
    private const string ROUTE = 'day-close.show';

    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly TeamScope $teams,
    ) {}

    public function key(): string {
        return 'day_correction';
    }

    public function label(): string {
        return (string) __('work-queue.source.day_correction');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled(self::ROUTE)
            && ($viewer->isAdmin() || $viewer->can(Permission::DayCloseApproveCorrection->value));
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $scope = $this->teams->userIds($viewer, Permission::DayCloseViewTeam, Permission::DayCloseViewOrganization);
        $pending = DayCorrectionRequest::query()
            ->where('organization_id', $organization->id)
            ->where('status', DayCorrectionStatus::Pending->value)
            ->where('requested_by_user_id', '!=', $viewer->id)
            ->whereHas('dayClosure', static fn ($query) => $query
                ->where('status', DayClosureStatus::Correction->value)
                ->where('user_id', '!=', $viewer->id)
                ->when($scope !== null, static fn ($inner) => $inner->whereIn('user_id', $scope)))
            ->with('dayClosure.user:id,name')
            ->orderBy('created_at')
            ->get();

        foreach ($pending as $correction) {
            $closure = $correction->dayClosure;
            if (! $closure instanceof DayClosure || ! $gate->allows('approveCorrection', $closure) || ! $gate->allows('view', $closure)) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: $correction->getMorphClass() . ':' . $correction->id,
                title: (string) __('work-queue.item.day_correction', ['name' => $closure->user->name ?? '—']),
                url: route(self::ROUTE, ['date' => $closure->day->toDateString(), 'user' => Sqid::encode(User::class, $closure->user_id)]),
                detail: CarbonFmt::fdate($closure->day),
                // Liegezeit (MVP-1670): letzte Änderung am Gegenstand.
                movedAt: WorkItem::lastMoved($correction->updated_at),
            );
        }
    }
}
