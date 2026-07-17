<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimsReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\Claims\{ClaimCase, ClaimFinancialOutcome, ClaimReportSnapshot};
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;

/**
 * Ursachen- und Qualitätsauswertung (Feature 072, MVP-254): Quote,
 * Ursachen, betroffene Produkte, Kosten, Bearbeitungsdauer, Frist-
 * überschreitungen und Wiederholfehler — mit CSV-Export (MVP-043:
 * BOM + Metazeilen + Audit) und eingefrorenem Berichtsstand
 * (Snapshot als Nachweis).
 */
class ClaimsReportController extends Controller {
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function index(Request $request): View|Response {
        Gate::authorize('viewAny', ClaimCase::class);

        [$from, $to] = $this->resolveRange($request);
        $data = $this->aggregate($from, $to);

        if ($request->query('export') === 'csv') {
            $filters = ['from' => $from->toDateString(), 'to' => $to->toDateString()];

            return $this->csv($data, $from, $to, $filters, $request);
        }

        return view('claims.reports', [
            'from' => $from,
            'to' => $to,
            'data' => $data,
            'snapshots' => ClaimReportSnapshot::query()->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    /** Berichtsstand einfrieren (Nachweis, MVP-254). */
    public function snapshot(Request $request): RedirectResponse {
        Gate::authorize('viewAny', ClaimCase::class);

        $actor = $request->user() ?? abort(401);
        [$from, $to] = $this->resolveRange($request);
        ClaimReportSnapshot::query()->create([
            'organization_id' => (int) $actor->organization_id,
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),
            'payload' => $this->aggregate($from, $to),
            'created_by' => $actor->id,
        ]);

        return back()->with('status', __('Berichtsstand eingefroren.'));
    }

    /** @return array<string, mixed> */
    private function aggregate(CarbonImmutable $from, CarbonImmutable $to): array {
        $cases = ClaimCase::query()
            ->with(['rootCause', 'defectType', 'article', 'supplier', 'customer'])
            ->whereBetween('reported_at', [$from, $to])
            ->get();

        $closed = $cases->filter(fn(ClaimCase $c): bool => $c->closed_at !== null);
        $byCause = $cases->filter(fn(ClaimCase $c): bool => $c->rootCause !== null)
            ->groupBy(fn(ClaimCase $c): string => (string) $c->rootCause?->label)
            ->map(fn($group) => $group->count())->sortDesc();
        $byDefect = $cases->filter(fn(ClaimCase $c): bool => $c->defectType !== null)
            ->groupBy(fn(ClaimCase $c): string => (string) $c->defectType?->label)
            ->map(fn($group) => $group->count())->sortDesc();
        $byArticle = $cases->filter(fn(ClaimCase $c): bool => $c->article !== null)
            ->groupBy(fn(ClaimCase $c): string => (string) $c->article?->name)
            ->map(fn($group) => $group->count())->sortDesc();
        $bySupplier = $cases->filter(fn(ClaimCase $c): bool => $c->supplier !== null)
            ->groupBy(fn(ClaimCase $c): string => (string) $c->supplier?->name)
            ->map(fn($group) => $group->count())->sortDesc();

        // Wiederholfehler: gleicher Artikel + gleiche Ursache mehrfach.
        $repeats = $cases->filter(fn(ClaimCase $c): bool => $c->article_id !== null && $c->root_cause_classification_id !== null)
            ->groupBy(fn(ClaimCase $c): string => $c->article_id . ':' . $c->root_cause_classification_id)
            ->filter(fn($group) => $group->count() > 1)
            ->map(fn($group) => [
                'article' => (string) $group->first()?->article?->name,
                'cause' => (string) $group->first()?->rootCause?->label,
                'count' => $group->count(),
            ])->values();

        $costs = ClaimFinancialOutcome::query()
            ->whereIn('claim_case_id', $cases->pluck('id'))
            ->where('status', 'executed')
            ->get();

        $durations = $closed->map(fn(ClaimCase $c): float => (float) $c->reported_at->diffInDays($c->closed_at, true));

        return [
            'total' => $cases->count(),
            'open' => $cases->filter(fn(ClaimCase $c): bool => $c->status->isOpen())->count(),
            'closed' => $closed->count(),
            'overdue' => $cases->filter(fn(ClaimCase $c): bool => $c->status->isOpen() && $c->due_at !== null && $c->due_at->isPast())->count(),
            'avg_duration_days' => $durations->isEmpty() ? null : round((float) $durations->avg(), 1),
            'cost_total' => (float) $costs->sum(fn(ClaimFinancialOutcome $o): float => (float) ($o->amount ?? 0)),
            'by_cause' => $byCause->all(),
            'by_defect' => $byDefect->all(),
            'by_article' => $byArticle->all(),
            'by_supplier' => $bySupplier->all(),
            'repeats' => $repeats->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $filters
     */
    private function csv(array $data, CarbonImmutable $from, CarbonImmutable $to, array $filters, Request $request): Response {
        $rows = [];
        $rows[] = ['Kennzahl', 'Wert'];
        $rows[] = ['Zeitraum', $from->toDateString() . ' – ' . $to->toDateString()];
        foreach (['total', 'open', 'closed', 'overdue', 'avg_duration_days', 'cost_total'] as $key) {
            $rows[] = [$key, (string) ($data[$key] ?? '')];
        }
        foreach (['by_cause' => 'Ursache', 'by_defect' => 'Mangelart', 'by_article' => 'Artikel', 'by_supplier' => 'Lieferant'] as $key => $label) {
            foreach ((array) $data[$key] as $name => $count) {
                $rows[] = [$label . ': ' . $name, (string) $count];
            }
        }
        foreach ((array) $data['repeats'] as $repeat) {
            $rows[] = ['Wiederholfehler: ' . $repeat['article'] . ' / ' . $repeat['cause'], (string) $repeat['count']];
        }

        return $this->csvWithMetadata($rows, 'reklamationsbericht.csv', 'claims-quality', $filters, $request);
    }
}
