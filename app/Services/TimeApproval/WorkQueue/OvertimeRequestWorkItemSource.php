<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OvertimeRequestWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\TimeApproval\WorkQueue;

use App\Enums\TimeApproval\OvertimeRequestStatus;
use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Approval\ApprovalStep;
use App\Models\Platform\{Organization, User};
use App\Models\Time\OvertimeRequest;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\{CarbonFmt, MorphMap};
use Illuminate\Support\Facades\Gate;

/**
 * Eingereichte Überstundenanträge anderer, über die der Nutzer entscheiden darf
 * (OvertimeRequestPolicy::decide). Wer eine Stufe schon freigab, sieht den
 * Antrag nicht mehr — die nächste Stufe muss eine andere Person geben.
 */
final class OvertimeRequestWorkItemSource implements WorkItemSource {
    private const string ROUTE = 'admin.overtime.index';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'overtime_request';
    }

    public function label(): string {
        return (string) __('work-queue.source.overtime_request');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled(self::ROUTE)
            && $viewer->can(Permission::OvertimeViewTeam->value)
            && ($viewer->isAdmin() || $viewer->can(Permission::OvertimeDecide->value));
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $pending = OvertimeRequest::query()
            ->where('organization_id', $organization->id)
            ->where('status', OvertimeRequestStatus::Submitted)
            ->where('user_id', '!=', $viewer->id)
            ->where('requested_by_user_id', '!=', $viewer->id)
            ->with('user:id,name')
            ->orderBy('scope_date')
            ->get();
        $decided = ApprovalStep::query()
            ->where('approvable_type', MorphMap::alias(OvertimeRequest::class))
            ->whereIn('approvable_id', $pending->modelKeys())
            ->where('decided_by', $viewer->id)
            ->pluck('approvable_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        foreach ($pending as $request) {
            if (in_array((int) $request->id, $decided, true) || ! $gate->allows('decide', $request)) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: $request->getMorphClass() . ':' . $request->id,
                title: (string) __('work-queue.item.overtime_request', ['name' => $request->user->name ?? '—']),
                url: route(self::ROUTE, ['status' => OvertimeRequestStatus::Submitted->value]),
                detail: CarbonFmt::fdate($request->scope_date) . ' · ' . __('work-queue.detail.minutes', ['minutes' => $request->minutes]),
                // Liegezeit (MVP-1670): letzte Änderung am Gegenstand.
                movedAt: WorkItem::lastMoved($request->updated_at),
            );
        }
    }
}
