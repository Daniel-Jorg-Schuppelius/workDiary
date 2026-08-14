<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentsReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\Investments\{InvestmentActual, InvestmentBudgetRequest, InvestmentCase, InvestmentDeviation};
use App\Models\User;
use App\Services\Investments\InvestmentService;
use App\Services\Reporting\ReportFilters;
use App\Support\ChartBucket;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Investitionsberichte (Feature 069, MVP-208): Pipeline, Budgetauslastung
 * (genehmigt/gebunden/Ist), offene Freigaben, Abweichungen — Drilldown
 * bis zur Akte, CSV-Export.
 */
class InvestmentsReportController extends Controller {
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function index(Request $request, InvestmentService $investments): View|Response {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->can(P::InvestmentViewAny->value), 403);

        // Zeitraum wirkt nur auf die Ist-Zeitreihe — die Akten-/Pipeline-Sicht
        // bleibt bewusst zeitraumunabhängig (Lebenszyklus statt Periode).
        [$from, $to] = $this->resolveRange($request);
        $filters = $this->standardFilters($request, ['status'], $from, $to, InvestmentCase::STATUSES);

        $pipeline = [];
        $rows = [];
        $totals = ['approved' => 0.0, 'committed' => 0.0, 'actual' => 0.0];

        $cases = InvestmentCase::query()
            ->with(['costCenter'])
            ->when($filters->status !== null, fn($q) => $q->where('status', $filters->status))
            ->orderByDesc('id')
            ->get();
        foreach ($cases as $case) {
            $pipeline[(string) $case->status] = ($pipeline[(string) $case->status] ?? 0) + 1;
            $projection = $investments->projection($case);
            if ($projection['approved'] > 0 || $projection['actual'] > 0 || $projection['committed'] > 0) {
                $rows[] = ['case' => $case, 'projection' => $projection];
                $totals['approved'] += $projection['approved'];
                $totals['committed'] += $projection['committed'];
                $totals['actual'] += $projection['actual'];
            }
        }

        $openApprovals = InvestmentBudgetRequest::query()->where('status', 'in_approval')->count();
        $openDeviations = InvestmentDeviation::query()->where('status', 'open')->count();

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            $csv = [['Akte', 'Status', 'Kostenstelle', 'Genehmigt €', 'Gebunden €', 'Ist €', 'Rest €']];
            foreach ($rows as $row) {
                $csv[] = [
                    $row['case']->title,
                    $row['case']->status,
                    $row['case']->costCenterDisplay() ?? '',
                    NumberHelper::toUSFormat((float) $row['projection']['approved'], 2),
                    NumberHelper::toUSFormat((float) $row['projection']['committed'], 2),
                    NumberHelper::toUSFormat((float) $row['projection']['actual'], 2),
                    $row['projection']['remaining'] !== null ? NumberHelper::toUSFormat((float) $row['projection']['remaining'], 2) : '',
                ];
            }

            return $this->csvWithMetadata($csv, 'investments.csv', 'investments', $filters->toAuditArray(), $request);
        }

        $statusOptions = [];
        foreach (InvestmentCase::STATUSES as $status) {
            $statusOptions[$status] = (string) __("values.$status");
        }

        return view('reports.investments', [
            'pipeline' => $pipeline,
            'rows' => $rows,
            'totals' => $totals,
            'openApprovals' => $openApprovals,
            'openDeviations' => $openDeviations,
            'standardFilters' => $filters,
            'filterFields' => ['status'],
            'statusOptions' => $statusOptions,
            'monthlyActualSeries' => $this->monthlyActualSeries($from, $to, $filters),
            'categoryVolumeSeries' => $this->categoryVolumeSeries($rows),
            'periodPhrase' => $this->periodPhrase($this->bucketGranularity($from, $to)),
            'periodAxis' => $this->periodAxisLabel($this->bucketGranularity($from, $to)),
        ]);
    }

    /**
     * Ist-Investitionen (€) je Monat des Zeitraums aus den erfassten
     * Ist-Werten (occurred_on). Negative Monatssummen (Korrekturen) werden
     * ausgeblendet — im Titel als „nur positive" dokumentiert. Leere Serie
     * statt Null-Achse (§Diagramm-UX).
     *
     * @return list<array{x: string, y: float}>
     */
    private function monthlyActualSeries(CarbonImmutable $from, CarbonImmutable $to, ReportFilters $filters): array {
        $granularity = $this->bucketGranularity($from, $to);
        $actuals = InvestmentActual::query()
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->when($filters->status !== null, fn($q) => $q->whereHas('investmentCase', fn($c) => $c->where('status', $filters->status)))
            ->get(['amount', 'occurred_on']);

        /** @var array<string, float> $byKey */
        $byKey = [];
        foreach ($actuals as $actual) {
            $key = ChartBucket::keyLabel($granularity, CarbonImmutable::parse((string) $actual->occurred_on))[0];
            $byKey[$key] = ($byKey[$key] ?? 0.0) + (float) $actual->amount;
        }
        if ($byKey === [] || array_sum($byKey) <= 0) {
            return [];
        }

        $series = [];
        foreach ($this->buildBucketsInRange($from, $to) as $bucket) {
            $sum = round($byKey[$bucket['key']] ?? 0.0, 2);
            if ($sum < 0) {
                continue; // bar kann keine negativen Werte darstellen (s. Titel).
            }
            $series[] = ['x' => $bucket['shortLabel'], 'y' => $sum];
        }

        return $series;
    }

    /**
     * Genehmigtes Volumen (€) je Kategorie — absteigend, nur Kategorien mit
     * genehmigtem Budget.
     *
     * @param  array<int, array{case: InvestmentCase, projection: array{approved: float, committed: float, actual: float, remaining: float|null}}>  $rows
     * @return list<array{x: string, y: float}>
     */
    private function categoryVolumeSeries(array $rows): array {
        /** @var array<string, float> $byCategory */
        $byCategory = [];
        foreach ($rows as $row) {
            $category = (string) $row['case']->category;
            $byCategory[$category] = ($byCategory[$category] ?? 0.0) + (float) $row['projection']['approved'];
        }

        return array_values(collect($byCategory)
            ->filter(static fn(float $sum): bool => $sum > 0)
            ->sortDesc()
            ->map(static fn(float $sum, string $category): array => [
                'x' => (string) __("values.$category"),
                'y' => round($sum, 2),
            ])
            ->values()
            ->all());
    }
}
