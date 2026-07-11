<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\Asset\AssetBlockReason;
use App\Http\Controllers\Controller;
use App\Models\AssetBlock;
use App\Models\AssetCompliance\{AssetComplianceAssignment, AssetComplianceProfile, AssetComplianceReportSnapshot, AssetInspectionEvent};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Auditbericht Prüfwesen (MVP-291): fällige/überfällige Prüfungen, Sperren,
 * Abweichungen, Prüfquote und Prüfer mit Drilldown — Snapshots frieren den
 * Stand ein (P2).
 */
class AssetComplianceReportController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', AssetComplianceProfile::class);

        [$from, $to] = $this->period($request);

        return view('asset-compliance.reports', array_merge($this->aggregate($from, $to), [
            'from' => $from,
            'to' => $to,
            'snapshots' => AssetComplianceReportSnapshot::query()->latest()->limit(10)->get(),
        ]));
    }

    public function snapshot(Request $request): RedirectResponse {
        Gate::authorize('viewAny', AssetComplianceProfile::class);

        $actor = $request->user() ?? abort(401);
        [$from, $to] = $this->period($request);

        AssetComplianceReportSnapshot::query()->create([
            'organization_id' => $actor->organization_id,
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),
            'payload' => $this->aggregate($from, $to),
            'created_by' => $actor->id,
        ]);

        return back()->with('status', __('Audit-Snapshot eingefroren.'));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(Request $request): array {
        $from = $request->filled('from') ? Carbon::parse($request->string('from')->toString()) : now()->subMonths(3);
        $to = $request->filled('to') ? Carbon::parse($request->string('to')->toString()) : now();

        return [$from->startOfDay(), $to->endOfDay()];
    }

    /** @return array<string, mixed> */
    private function aggregate(Carbon $from, Carbon $to): array {
        $assignments = AssetComplianceAssignment::query()
            ->active()
            ->with(['asset', 'profile'])
            ->get();

        $events = AssetInspectionEvent::query()
            ->whereBetween('performed_at', [$from, $to])
            ->with(['asset', 'performer', 'certificate'])
            ->get();

        $failed = $events->filter(fn (AssetInspectionEvent $e) => ! $e->result->isPassed());

        return [
            'assignmentCount' => $assignments->count(),
            'dueSoonCount' => $assignments->filter(fn ($a) => $a->isDueSoon())->count(),
            'overdueCount' => $assignments->filter(fn ($a) => $a->isOverdue())->count(),
            'blockedCount' => AssetBlock::query()->active()
                ->whereIn('reason', [AssetBlockReason::InspectionOverdue->value, AssetBlockReason::InspectionFailed->value])
                ->count(),
            'inspectionCount' => $events->count(),
            'failedCount' => $failed->count(),
            'passRate' => $events->isNotEmpty()
                ? round($events->filter(fn ($e) => $e->result->isPassed())->count() / $events->count() * 100, 1)
                : null,
            'certificateCount' => $events->filter(fn ($e) => $e->certificate !== null)->count(),
            'byKind' => $assignments
                ->groupBy(fn ($a) => $a->profile->inspection_kind->value ?? '—')
                ->map->count()
                ->all(),
            'byInspector' => $events
                ->groupBy(fn ($e) => $e->performer->name ?? $e->external_inspector_name ?? '—')
                ->map->count()
                ->sortDesc()
                ->take(10)
                ->all(),
            'deviations' => $failed->map(fn (AssetInspectionEvent $e) => [
                'asset' => $e->asset->name ?? '—',
                'performed_at' => $e->performed_at->toDateTimeString(),
                'note' => $e->note,
            ])->values()->all(),
        ];
    }
}
