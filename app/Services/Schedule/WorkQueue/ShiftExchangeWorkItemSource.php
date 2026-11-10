<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftExchangeWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Schedule\WorkQueue;

use App\Enums\Shift\ShiftExchangeStatus;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Models\Schedule\ShiftExchange;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\CarbonFmt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Schichttausch in zwei Schritten (requested → accepted → approved): die an
 * den Nutzer gerichtete Anfrage wartet auf seine Annahme (persönlich), die
 * angenommene auf die Freigabe der Planung (ShiftExchangePolicy::decide).
 * Offene Abgaben ohne Zielperson sind ein Angebot, kein Vorgang; wer selbst
 * am Tausch beteiligt ist, gibt ihn nicht frei.
 */
final class ShiftExchangeWorkItemSource implements WorkItemSource {
    private const string ROUTE = 'schedule.exchanges.index';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'shift_exchange';
    }

    public function label(): string {
        return (string) __('work-queue.source.shift_exchange');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled(self::ROUTE) && Gate::forUser($viewer)->allows('viewAny', ShiftExchange::class);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $open = ShiftExchange::query()
            ->where('organization_id', $organization->id)
            ->where('requested_by_user_id', '!=', $viewer->id)
            ->where(static fn ($query) => $query
                ->where(static fn ($accept) => $accept->where('status', ShiftExchangeStatus::Requested->value)->where('target_user_id', $viewer->id))
                ->orWhere(static fn ($decide) => $decide->where('status', ShiftExchangeStatus::Accepted->value)->where('target_user_id', '!=', $viewer->id)))
            ->with(['requester:id,name', 'targetUser:id,name', 'scheduledShift.shiftType'])
            ->orderBy('id')
            ->get();

        foreach ($open as $exchange) {
            $toAccept = $exchange->status === ShiftExchangeStatus::Requested;
            if (! $gate->allows($toAccept ? 'accept' : 'decide', $exchange)) {
                continue;
            }
            $shift = $exchange->scheduledShift;
            $requester = $exchange->requester->name ?? '—';
            yield new WorkItem(
                source: $this->key(),
                key: $exchange->getMorphClass() . ':' . $exchange->id,
                title: $toAccept
                    ? (string) __('work-queue.item.shift_exchange.accept', ['name' => $requester])
                    : (string) __('work-queue.item.shift_exchange.approve', ['from' => $requester, 'to' => $exchange->targetUser->name ?? '—']),
                url: route(self::ROUTE),
                detail: $shift !== null ? trim(CarbonFmt::fdate($shift->date) . ' ' . ($shift->shiftType->abbreviation ?? $shift->shiftType->name ?? '')) : null,
                dueAt: $shift !== null ? CarbonImmutable::instance($shift->date) : null,
                personal: $toAccept,
            );
        }
    }
}
