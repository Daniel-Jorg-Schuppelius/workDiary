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
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\Asset\AssetBlock;
use App\Models\AssetCompliance\{AssetComplianceAssignment, AssetComplianceProfile, AssetComplianceReportSnapshot, AssetInspectionEvent};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Auditbericht Prüfwesen (MVP-291): fällige/überfällige Prüfungen, Sperren,
 * Abweichungen, Prüfquote, Prüfkosten und Prüfer mit Drilldown — Snapshots
 * frieren den Stand ein (P2); CSV-Export nach dem Muster der Nachbarmodule
 * (Vollaudit 2026-07, M33).
 */
class AssetComplianceReportController extends Controller {
    use \App\Http\Controllers\Reporting\Concerns\WritesReportCsv;

    use ResolvesGlobalDateRange;

    public function index(Request $request): View|\Illuminate\Http\Response {
        Gate::authorize('viewAny', AssetComplianceProfile::class);

        [$from, $to] = $this->period($request);
        $aggregate = $this->aggregate($from, $to);

        // CSV-Export (MVP-292; Vollaudit 2026-07, M33).
        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($aggregate, $from, $to, $request);
        }

        return view('asset-compliance.reports', array_merge($aggregate, [
            'from' => $from,
            'to' => $to,
            'snapshots' => AssetComplianceReportSnapshot::query()->latest()->limit(10)->get(),
        ]));
    }

    /** @param array<string, mixed> $aggregate */
    private function exportCsv(array $aggregate, Carbon $from, Carbon $to, Request $request): \Illuminate\Http\Response {
        $num = static fn($v): string => $v === null ? '' : \CommonToolkit\Helper\Data\NumberHelper::toUSFormat((float) $v, 2);

        $metricLabel = (string) __('reporting.csv.metric');
        $rows = [[(string) __('reporting.csv.area'), (string) __('reporting.csv.key'), (string) __('reporting.csv.value')]];
        $rows[] = [$metricLabel, (string) __('reporting.csv.active_obligations'), (string) $aggregate['assignmentCount']];
        $rows[] = [$metricLabel, (string) __('reporting.csv.due_soon'), (string) $aggregate['dueSoonCount']];
        $rows[] = [$metricLabel, (string) __('reporting.csv.overdue'), (string) $aggregate['overdueCount']];
        $rows[] = [$metricLabel, (string) __('reporting.csv.blocked'), (string) $aggregate['blockedCount']];
        $rows[] = [$metricLabel, (string) __('reporting.csv.inspections'), (string) $aggregate['inspectionCount']];
        $rows[] = [$metricLabel, (string) __('reporting.csv.failed'), (string) $aggregate['failedCount']];
        $rows[] = [$metricLabel, (string) __('reporting.csv.pass_rate_percent'), $num($aggregate['passRate'])];
        $rows[] = [$metricLabel, (string) __('reporting.csv.certificates'), (string) $aggregate['certificateCount']];
        $rows[] = [$metricLabel, (string) __('reporting.csv.inspection_cost_eur'), $num($aggregate['totalCost'])];
        foreach ($aggregate['byKind'] as $kind => $count) {
            $rows[] = [(string) __('reporting.csv.obligations_by_kind'), (string) $kind, (string) $count];
        }
        foreach ($aggregate['costByKind'] as $kind => $cost) {
            $rows[] = [(string) __('reporting.csv.inspection_cost_by_kind_eur'), (string) $kind, $num($cost)];
        }
        foreach ($aggregate['byInspector'] as $name => $count) {
            $rows[] = [(string) __('reporting.csv.inspections_by_inspector'), (string) $name, (string) $count];
        }
        foreach ($aggregate['deviations'] as $deviation) {
            $rows[] = [(string) __('reporting.csv.deviation'), $deviation['asset'] . ' · ' . $deviation['performed_at'], (string) ($deviation['note'] ?? '')];
        }

        return $this->csvWithMetadata(
            $rows,
            sprintf('pruefwesen_%s_%s.csv', $from->toDateString(), $to->toDateString()),
            'asset-compliance',
            ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            $request,
        );
    }

    public function snapshot(Request $request): RedirectResponse {
        Gate::authorize('viewAny', AssetComplianceProfile::class);

        $actor = $request->user() ?? abort(401);
        [$from, $to] = $this->period($request);

        AssetComplianceReportSnapshot::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),
            'payload' => $this->aggregate($from, $to),
            'created_by' => $actor->id,
        ]);

        return back()->with('status', __('Audit-Snapshot eingefroren.'));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(Request $request): array {
        // W2.1: einheitlicher Parameter-Guard, fachlicher 3-Monats-Default bleibt.
        [$rangeFrom, $rangeTo] = $this->resolveRangeWithDefault($request, static fn (): array => [
            \Carbon\CarbonImmutable::now()->subMonths(3),
            \Carbon\CarbonImmutable::now(),
        ]);
        $from = Carbon::instance($rangeFrom->toDateTime());
        $to = Carbon::instance($rangeTo->toDateTime());

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
            ->with(['asset', 'performer', 'certificate', 'assignment.profile'])
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
            // Prüfkosten (MVP-291; Vollaudit 2026-07, M33): Summe + je Prüfart.
            'totalCost' => round((float) $events->sum(fn (AssetInspectionEvent $e): float => (float) $e->cost), 2),
            'costByKind' => $events
                ->filter(fn (AssetInspectionEvent $e): bool => $e->cost !== null)
                ->groupBy(fn (AssetInspectionEvent $e) => $e->assignment?->profile?->inspection_kind->value ?? '—')
                ->map(fn ($group): float => round((float) $group->sum(fn (AssetInspectionEvent $e): float => (float) $e->cost), 2))
                ->all(),
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
