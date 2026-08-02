<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UtilizationReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Reporting\{ReportTargetMetric, ReportTargetScope};
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\User;
use App\Services\Reporting\{ReportFilters, ReportTargetEvaluator, UtilizationReportBuilder};
use App\Support\CarbonFmt;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Auslastung & Realisierung (MVP-467, Feature 002): Reicht die abrechenbare
 * Auslastung, und kommt die erfasste Zeit auch auf der Rechnung an?
 * Keine Rangliste: neutrale Sortierung nach Name, Gesamtwert zuerst.
 */
class UtilizationReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function __construct(
        private readonly UtilizationReportBuilder $builder,
        private readonly ReportTargetEvaluator $targets,
    ) {}

    public function index(Request $request): View|Response|SymfonyResponse {
        $auth = Auth::user();
        if (! $auth instanceof User || ! $auth->isAdmin()) {
            Gate::authorize('viewAny', User::class);
        }

        [$from, $to] = $this->resolveRange($request);
        $label = CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to);

        $filterFields = ['user', 'team'];
        $filters = $this->standardFilters($request, $filterFields, $from, $to);

        $users = User::query()
            ->whereNull('deactivated_at')
            ->when($filters->userId !== null, fn($q) => $q->where('id', $filters->userId))
            ->when($filters->userId === null && $filters->teamId !== null, fn($q) => $q->whereIn('id', $filters->teamUserIds()))
            ->orderBy('name')
            ->get();

        $result = $this->builder->build($from, $to, $users);

        $targetPool = $this->targets->load(ReportTargetMetric::Utilization, $to);
        $orgEval = $this->targets->evaluate(
            ReportTargetMetric::Utilization,
            $this->targets->resolve($targetPool, ReportTargetScope::Org, null),
            $result['totals']['utilization'],
        );
        $billableEval = $this->targets->compare(ReportTargetMetric::BillableRate, $result['totals']['billableRate'], on: $to);

        $exportFilters = $filters->toAuditArray();

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($result, $from->toDateString(), $to->toDateString(), $exportFilters, $request);
        }

        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($result, $targetPool, $label, $from->toDateString(), $to->toDateString(), $exportFilters, $request);
        }

        return view('reports.utilization', [
            'rows' => $result['rows'],
            'totals' => $result['totals'],
            'hasInvoiceData' => $result['hasInvoiceData'],
            'orgEval' => $orgEval,
            'billableEval' => $billableEval,
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'standardFilters' => $filters,
            'filterFields' => $filterFields,
            'bulletSeries' => $this->bulletSeries($result['rows'], $targetPool),
            'trendSeries' => $this->trendSeries($result['monthly'], $filters),
            'boxSeries' => $this->monthUrls($result['monthlyBoxes'], $filters),
            ...$this->standardFilterOptions($filterFields, $filters),
        ]);
    }

    /**
     * Drilldown-Link auf denselben Report, eingeschränkt auf einen Monat
     * (from/to-Override der globalen Zeitraumwahl, MVP-470).
     */
    private function monthUrl(string $month, ReportFilters $filters): string {
        $start = \Carbon\CarbonImmutable::parse($month . '-01');

        return route('reports.utilization', array_merge($filters->toQueryParams(), [
            'from' => $start->toDateString(),
            'to' => $start->endOfMonth()->toDateString(),
        ]));
    }

    /**
     * @param  list<array{x:string, min:float, q1:float, median:float, q3:float, max:float, n:int}>  $boxes
     * @return list<array{x:string, min:float, q1:float, median:float, q3:float, max:float, n:int, url:string}>
     */
    private function monthUrls(array $boxes, ReportFilters $filters): array {
        return array_map(fn(array $box): array => [...$box, 'url' => $this->monthUrl($box['x'], $filters)], $boxes);
    }

    /**
     * Auslastung je Person gegen den aufgelösten Zielwert (user-spezifisch,
     * sonst org-weit) — Bullet-Kontrakt.
     *
     * @param  list<array{userId:int, userName:string, targetMinutes:int, trackedMinutes:int, billableMinutes:int, invoicedMinutes:int, utilization:?float, billableRate:?float, realization:?float}>  $rows
     * @param  \Illuminate\Support\Collection<int, \App\Models\ReportTarget>  $targetPool
     * @return list<array{x: string, y: float, target: ?float, url: string}>
     */
    private function bulletSeries(array $rows, \Illuminate\Support\Collection $targetPool): array {
        return array_values(collect($rows)
            ->filter(static fn(array $row): bool => $row['utilization'] !== null)
            ->map(function (array $row) use ($targetPool): array {
                $target = $this->targets->resolve($targetPool, ReportTargetScope::User, $row['userId']);

                return [
                    'x' => $row['userName'],
                    'y' => $row['utilization'],
                    'target' => $target !== null ? (float) $target->target_value : null,
                    'url' => route('reports.month-by-user-team', ['user' => \App\Support\Sqid::encode(User::class, $row['userId'])]),
                ];
            })
            ->all());
    }

    /**
     * @param  list<array{month:string, utilization:?float, billableRate:?float}>  $monthly
     * @return list<array{x: string, y: float, url: string}>
     */
    private function trendSeries(array $monthly, ReportFilters $filters): array {
        $series = array_values(array_filter(array_map(fn(array $m): ?array => $m['utilization'] === null ? null : [
            'x' => $m['month'],
            'y' => $m['utilization'],
            'url' => $this->monthUrl($m['month'], $filters),
        ], $monthly)));

        return count($series) > 1 ? $series : []; // Ein-Punkt-Linie sagt nichts — Leerzustand.
    }

    /**
     * @param  array{rows: list<array{userId:int, userName:string, targetMinutes:int, trackedMinutes:int, billableMinutes:int, invoicedMinutes:int, utilization:?float, billableRate:?float, realization:?float}>, totals: array{targetMinutes:int, trackedMinutes:int, billableMinutes:int, invoicedMinutes:int, utilization:?float, billableRate:?float, realization:?float}, hasInvoiceData: bool, monthly: list<array{month:string, utilization:?float, billableRate:?float}>, monthlyBoxes: list<array{x:string, min:float, q1:float, median:float, q3:float, max:float, n:int}>}  $result
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $result, string $from, string $to, array $filters, Request $request): Response {
        $filename = sprintf('auslastung_%s_%s.csv', $from, $to);
        $out = [];
        $out[] = ['Person', 'SollMinuten', 'ErfassteMinuten', 'AbrechenbarMinuten', 'FakturiertMinuten', 'AuslastungProzent', 'AbrechenbareQuoteProzent', 'RealisierungProzent'];

        foreach ($result['rows'] as $row) {
            $out[] = [
                $row['userName'],
                $row['targetMinutes'],
                $row['trackedMinutes'],
                $row['billableMinutes'],
                $row['invoicedMinutes'],
                $row['utilization'] !== null ? NumberHelper::toUSFormat($row['utilization'], 1) : '',
                $row['billableRate'] !== null ? NumberHelper::toUSFormat($row['billableRate'], 1) : '',
                $row['realization'] !== null ? NumberHelper::toUSFormat($row['realization'], 1) : '',
            ];
        }

        $t = $result['totals'];
        $out[] = ['GESAMT', $t['targetMinutes'], $t['trackedMinutes'], $t['billableMinutes'], $t['invoicedMinutes'],
            $t['utilization'] !== null ? NumberHelper::toUSFormat($t['utilization'], 1) : '',
            $t['billableRate'] !== null ? NumberHelper::toUSFormat($t['billableRate'], 1) : '',
            $t['realization'] !== null ? NumberHelper::toUSFormat($t['realization'], 1) : ''];

        return $this->csvWithMetadata($out, $filename, 'utilization', $filters, $request);
    }

    /**
     * @param  array{rows: list<array{userId:int, userName:string, targetMinutes:int, trackedMinutes:int, billableMinutes:int, invoicedMinutes:int, utilization:?float, billableRate:?float, realization:?float}>, totals: array{targetMinutes:int, trackedMinutes:int, billableMinutes:int, invoicedMinutes:int, utilization:?float, billableRate:?float, realization:?float}, hasInvoiceData: bool, monthly: list<array{month:string, utilization:?float, billableRate:?float}>, monthlyBoxes: list<array{x:string, min:float, q1:float, median:float, q3:float, max:float, n:int}>}  $result
     * @param  \Illuminate\Support\Collection<int, \App\Models\ReportTarget>  $targetPool
     * @param  array<string, mixed>  $filters
     */
    private function exportPdf(array $result, \Illuminate\Support\Collection $targetPool, string $label, string $from, string $to, array $filters, Request $request): SymfonyResponse {
        $filename = sprintf('auslastung_%s_%s.pdf', $from, $to);

        return $this->pdfDownload('reports.pdf.utilization', [
            'rows' => $result['rows'],
            'totals' => $result['totals'],
            'hasInvoiceData' => $result['hasInvoiceData'],
            'label' => $label,
            'chart' => [
                'type' => 'bullet-h',
                'title' => __('Auslastung je Person gegen Zielwert'),
                'unit' => '%',
                'xLabel' => __('Person'),
                'yLabel' => __('Auslastung %'),
                'targetLabel' => __('Ziel'),
                'series' => $this->bulletSeries($result['rows'], $targetPool),
            ],
        ], $filename, 'landscape', $request, 'utilization', $filters);
    }
}
