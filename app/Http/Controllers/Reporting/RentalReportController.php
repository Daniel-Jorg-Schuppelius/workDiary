<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\Rental\{RentalCaseStatus, RentalChargeStatus, RentalReturnFollowUp};
use App\Http\Controllers\Controller;
use App\Models\Rental\{RentalCase, RentalCharge, RentalProfile, RentalReportSnapshot, RentalReturnReport};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Verleihberichte (MVP-268): Auslastung, Umsatz, Überfälligkeit und Schäden
 * je Zeitraum mit Drilldown bis zur Akte; Snapshots frieren den Stand ein
 * (P2 — spätere Datenänderungen bewerten alte Berichte nicht um).
 */
class RentalReportController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', RentalCase::class);

        [$from, $to] = $this->period($request);

        return view('rental.reports', array_merge($this->aggregate($from, $to), [
            'from' => $from,
            'to' => $to,
            'snapshots' => RentalReportSnapshot::query()->latest()->limit(10)->get(),
        ]));
    }

    public function snapshot(Request $request): RedirectResponse {
        Gate::authorize('viewAny', RentalCase::class);

        $actor = $request->user() ?? abort(401);
        [$from, $to] = $this->period($request);

        RentalReportSnapshot::query()->create([
            'organization_id' => $actor->organization_id,
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),
            'payload' => $this->aggregate($from, $to),
            'created_by' => $actor->id,
        ]);

        return back()->with('status', __('Berichts-Snapshot eingefroren.'));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(Request $request): array {
        $from = $request->filled('from') ? Carbon::parse($request->string('from')->toString()) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->string('to')->toString()) : now()->endOfMonth();

        return [$from->startOfDay(), $to->endOfDay()];
    }

    /** @return array<string, mixed> */
    private function aggregate(Carbon $from, Carbon $to): array {
        $cases = RentalCase::query()
            ->with(['customer', 'caseAssets.asset.rentalProfile'])
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get();

        $charges = RentalCharge::query()
            ->whereIn('status', [RentalChargeStatus::Invoiced->value, RentalChargeStatus::Transferred->value, RentalChargeStatus::Released->value])
            ->whereHas('rentalCase', fn($q) => $q->where('starts_at', '<', $to)->where('ends_at', '>', $from))
            ->with('rentalCase.customer')
            ->get();

        $returns = RentalReturnReport::query()
            ->whereBetween('reported_at', [$from, $to])
            ->with('asset')
            ->get();

        $periodDays = max(1, (int) $from->diffInDays($to) + 1);
        $rentableCount = max(1, RentalProfile::query()->rentable()->count());

        // Auslastung: belegte Gerätetage im Zeitraum / verfügbare Gerätetage.
        $rentedDays = 0;
        foreach ($cases as $case) {
            if (! $case->status->blocksAvailability() && ! in_array($case->status, [RentalCaseStatus::Returned, RentalCaseStatus::Closed], true)) {
                continue;
            }
            $start = $case->starts_at->copy()->max($from);
            $end = ($case->actual_return_at ?? $case->ends_at)->copy()->min($to);
            if ($end > $start) {
                $rentedDays += (int) ceil($start->diffInHours($end) / 24) * max(1, $case->caseAssets->count());
            }
        }

        $revenueByKind = $charges
            ->groupBy(fn (RentalCharge $c) => $c->kind->value)
            ->map(fn ($group) => round((float) $group->sum(fn (RentalCharge $c) => (float) $c->amount), 2));

        $revenueByCustomer = $charges
            ->groupBy(fn (RentalCharge $c) => $c->rentalCase->customer->name ?? '—')
            ->map(fn ($group) => round((float) $group->sum(fn (RentalCharge $c) => (float) $c->amount), 2))
            ->sortDesc();

        $damageReturns = $returns->filter(
            fn (RentalReturnReport $r) => $r->follow_up !== RentalReturnFollowUp::None || filled($r->damages),
        );

        return [
            'caseCount' => $cases->count(),
            'overdueCount' => $cases->where('status', RentalCaseStatus::Overdue)->count(),
            'utilization' => round(min(100, $rentedDays / ($periodDays * $rentableCount) * 100), 1),
            'rentedDays' => $rentedDays,
            'revenueTotal' => round((float) $charges->sum(fn (RentalCharge $c) => (float) $c->amount), 2),
            'revenueByKind' => $revenueByKind->all(),
            'revenueByCustomer' => $revenueByCustomer->take(10)->all(),
            'damageCount' => $damageReturns->count(),
            'damageByAsset' => $damageReturns
                ->groupBy(fn (RentalReturnReport $r) => $r->asset->name ?? '—')
                ->map->count()
                ->sortDesc()
                ->take(10)
                ->all(),
            'maintenanceBlocked' => \App\Models\Rental\RentalReservation::query()
                ->active()
                ->whereIn('kind', [\App\Enums\Rental\RentalReservationKind::Maintenance->value, \App\Enums\Rental\RentalReservationKind::Cleaning->value])
                ->where('starts_at', '<', $to)
                ->where('ends_at', '>', $from)
                ->count(),
        ];
    }
}
