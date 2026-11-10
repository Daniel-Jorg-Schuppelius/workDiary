<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeCorrectionWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\TimeApproval\WorkQueue;

use App\Enums\TimeApproval\TimeCorrectionStatus;
use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Approval\ApprovalStep;
use App\Models\Platform\{Organization, User};
use App\Models\Time\TimeCorrectionRequest;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Users\TeamScope;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\{CarbonFmt, MorphMap};
use Illuminate\Support\Facades\Gate;

/**
 * Zeitkorrekturen anderer: eingereichte zur Entscheidung (approve/reject der
 * TimeCorrectionRequestPolicy, Team-Recht nur fürs eigene Team) und
 * freigegebene, die noch angewendet werden müssen (apply). Wer eine Stufe
 * schon freigab, entscheidet nicht noch einmal.
 */
final class TimeCorrectionWorkItemSource implements WorkItemSource {
    private const string ROUTE = 'admin.corrections.index';

    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly TeamScope $teams,
    ) {}

    public function key(): string {
        return 'time_correction';
    }

    public function label(): string {
        return (string) __('work-queue.source.time_correction');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled(self::ROUTE)
            && ($viewer->isAdmin()
                || $viewer->can(Permission::CorrectionApprove->value)
                || $viewer->can(Permission::CorrectionReject->value)
                || $viewer->can(Permission::CorrectionApplySystem->value));
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $scope = $this->teams->userIds($viewer, Permission::CorrectionViewTeam, Permission::CorrectionViewOrganization);
        $open = TimeCorrectionRequest::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [TimeCorrectionStatus::Submitted->value, TimeCorrectionStatus::Approved->value])
            ->where('user_id', '!=', $viewer->id)
            ->where('requested_by_user_id', '!=', $viewer->id)
            ->when($scope !== null, static fn ($query) => $query->whereIn('user_id', $scope))
            ->with('user:id,name')
            ->orderBy('scope_date')
            ->get();
        $decided = ApprovalStep::query()
            ->where('approvable_type', MorphMap::alias(TimeCorrectionRequest::class))
            ->whereIn('approvable_id', $open->modelKeys())
            ->where('decided_by', $viewer->id)
            ->pluck('approvable_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        foreach ($open as $correction) {
            $toApply = $correction->status === TimeCorrectionStatus::Approved;
            $actionable = $toApply
                ? $gate->allows('apply', $correction)
                : ! in_array((int) $correction->id, $decided, true) && $gate->any(['approve', 'reject'], $correction);
            if (! $actionable || ! $gate->allows('view', $correction)) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: $correction->getMorphClass() . ':' . $correction->id,
                title: (string) __($toApply ? 'work-queue.item.time_correction_apply' : 'work-queue.item.time_correction', ['name' => $correction->user->name ?? '—']),
                url: route('admin.corrections.show', $correction),
                detail: CarbonFmt::fdate($correction->scope_date),
            );
        }
    }
}
