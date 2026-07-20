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
 * Abweichungen, Prüfquote, Prüfkosten und Prüfer mit Drilldown — Snapshots
 * frieren den Stand ein (P2); CSV-Export nach dem Muster der Nachbarmodule
 * (Vollaudit 2026-07, M33).
 */
class AssetComplianceReportController extends Controller {
    use \App\Http\Controllers\Reporting\Concerns\WritesReportCsv;

    public function index(Request $request): View|\Illuminate\Http\Response {
        Gate::authorize('viewAny', AssetComplianceProfile::class);

        [$from, $to] = $this->period($request);
        $aggregate = $this->aggregate($from, $to);

        // CSV-Export (MVP-292; Vollaudit 2026-07, M33).
        if ($request->query('export') === 'csv') {
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

        $rows = [['Bereich', 'Schlüssel', 'Wert']];
        $rows[] = ['Kennzahl', 'AktivePflichten', (string) $aggregate['assignmentCount']];
        $rows[] = ['Kennzahl', 'BaldFaellig', (string) $aggregate['dueSoonCount']];
        $rows[] = ['Kennzahl', 'Ueberfaellig', (string) $aggregate['overdueCount']];
        $rows[] = ['Kennzahl', 'Gesperrt', (string) $aggregate['blockedCount']];
        $rows[] = ['Kennzahl', 'Pruefungen', (string) $aggregate['inspectionCount']];
        $rows[] = ['Kennzahl', 'Fehlgeschlagen', (string) $aggregate['failedCount']];
        $rows[] = ['Kennzahl', 'BestehensquoteProzent', $num($aggregate['passRate'])];
        $rows[] = ['Kennzahl', 'Zertifikate', (string) $aggregate['certificateCount']];
        $rows[] = ['Kennzahl', 'PruefkostenEUR', $num($aggregate['totalCost'])];
        foreach ($aggregate['byKind'] as $kind => $count) {
            $rows[] = ['PflichtenJeArt', (string) $kind, (string) $count];
        }
        foreach ($aggregate['costByKind'] as $kind => $cost) {
            $rows[] = ['PruefkostenJeArtEUR', (string) $kind, $num($cost)];
        }
        foreach ($aggregate['byInspector'] as $name => $count) {
            $rows[] = ['PruefungenJePruefer', (string) $name, (string) $count];
        }
        foreach ($aggregate['deviations'] as $deviation) {
            $rows[] = ['Abweichung', $deviation['asset'] . ' · ' . $deviation['performed_at'], (string) ($deviation['note'] ?? '')];
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
