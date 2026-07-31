<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{MaterialUsage, Project};
use App\Services\Reporting\ReportFilters;
use Carbon\{Carbon, CarbonImmutable};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Materialverbrauch im Zeitraum, basierend auf MaterialUsage über Timesheet.work_date.
 */
class MaterialReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        [$scope, $isAdmin] = $this->resolveScopeWithAdmin($request);

        [$fromDate, $toDate] = $this->resolveRange($request);
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $filters = $this->standardFilters($request, ['customer', 'project'], $fromDate, $toDate, scope: $scope);

        $aggregation = $this->aggregate($from, $to, $scope, $userId, $filters);
        $paretoSeries = $this->materialValueSeries($aggregation['rows']);
        $exportFilters = array_merge(['scope' => $scope], $filters->toAuditArray());

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($aggregation, $from, $to, $request, $exportFilters);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($aggregation, $from, $to, $scope, $paretoSeries, $request, $exportFilters);
        }

        return view('reports.materials', [
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'rows' => $aggregation['rows'],
            'totals' => $aggregation['totals'],
            'standardFilters' => $filters,
            'filterFields' => ['customer', 'project'],
            'materialValueSeries' => $paretoSeries,
            'monthlyCostSeries' => $this->monthlyCostSeries($aggregation['monthly'], $fromDate, $toDate),
            ...$this->standardFilterOptions(['customer', 'project'], $filters),
        ]);
    }

    /**
     * @return array{
     *   rows: array<int, array{
     *     material_id: int|null,
     *     sku: string|null,
     *     name: string,
     *     unit: string,
     *     quantity: float,
     *     line_total_net: float,
     *     usage_count: int
     *   }>,
     *   totals: array{materials:int, usage_count:int, line_total_net:float},
     *   monthly: array<string, float>
     * }
     */
    private function aggregate(string $from, string $to, string $scope, int $userId, ReportFilters $filters): array {
        $q = MaterialUsage::query()
            ->with(['material:id,sku,name,unit', 'timesheet:id,work_date'])
            ->whereHas('timesheet', function ($w) use ($from, $to, $scope, $userId, $filters): void {
                $w->whereBetween('work_date', [$from, $to]);
                if ($scope === 'mine') {
                    $w->where('user_id', $userId);
                }
                // Standardfilter (Feature 002): Projekt direkt, Kunde über die
                // org-gescopte Projektliste (Timesheet kennt keinen Kunden).
                if ($filters->projectId !== null) {
                    $w->where('project_id', $filters->projectId);
                } elseif ($filters->customerId !== null) {
                    $w->whereIn('project_id', Project::query()->where('customer_id', $filters->customerId)->select('id'));
                }
            });

        /** @var Collection<int, MaterialUsage> $usages */
        $usages = $q->get(['id', 'material_id', 'timesheet_id', 'description', 'quantity', 'unit', 'unit_price', 'line_total_net']);

        /** @var array<string, array{material_id: int|null, sku: string|null, name: string, unit: string, quantity: float, line_total_net: float, usage_count: int}> $byKey */
        $byKey = [];
        /** @var array<string, float> $byMonth */
        $byMonth = [];
        $sumNet = 0.0;
        foreach ($usages as $u) {
            $workDate = $u->timesheet?->work_date;
            if ($workDate !== null) {
                $monthKey = Carbon::parse((string) $workDate)->format('Y-m');
                $byMonth[$monthKey] = ($byMonth[$monthKey] ?? 0.0) + ($u->line_total_net?->toFloat() ?? 0.0);
            }
            $mid = $u->material_id !== null ? (int) $u->material_id : null;
            $material = $mid !== null ? $u->material : null;
            $sku = $material?->sku;
            $name = $material !== null ? $material->name : (string) ($u->description ?? __('Ohne Material'));
            $unit = (string) $u->unit;
            $key = ($mid ?? 'null') . '|' . $unit;

            if (! isset($byKey[$key])) {
                $byKey[$key] = [
                    'material_id' => $mid,
                    'sku' => $sku,
                    'name' => $name,
                    'unit' => $unit,
                    'quantity' => 0.0,
                    'line_total_net' => 0.0,
                    'usage_count' => 0,
                ];
            }
            $byKey[$key]['quantity'] += ($u->quantity?->getValue()->toFloat() ?? 0.0);
            $byKey[$key]['line_total_net'] += ($u->line_total_net?->toFloat() ?? 0.0);
            $byKey[$key]['usage_count']++;
            $sumNet += ($u->line_total_net?->toFloat() ?? 0.0);
        }

        $rows = array_values($byKey);
        usort($rows, static fn($a, $b): int => $b['line_total_net'] <=> $a['line_total_net']);

        $distinctMaterials = count(array_unique(array_map(static fn($r): string => ($r['material_id'] ?? 'null') . '', $rows)));

        return [
            'rows' => $rows,
            'totals' => [
                'materials' => $distinctMaterials,
                'usage_count' => $usages->count(),
                'line_total_net' => $sumNet,
            ],
            'monthly' => $byMonth,
        ];
    }

    /**
     * Verbrauchswert je Material (Top 20) — Pareto am Screen, bar-h im PDF.
     *
     * @param  array<int, array{material_id:int|null, sku:string|null, name:string, unit:string, quantity:float, line_total_net:float, usage_count:int}>  $rows
     * @return list<array{x: string, y: float}>
     */
    private function materialValueSeries(array $rows): array {
        return array_values(collect($rows)
            ->filter(static fn(array $r): bool => $r['line_total_net'] > 0)
            ->sortByDesc('line_total_net')
            ->take(20)
            ->map(static fn(array $r): array => [
                'x' => $r['name'],
                'y' => round((float) $r['line_total_net'], 2),
            ])
            ->all());
    }

    /**
     * Materialkosten (netto, €) je Monat über den Zeitraum — leere Serie
     * statt Null-Achse (§Diagramm-UX).
     *
     * @param  array<string, float>  $byMonth
     * @return list<array{x: string, y: float}>
     */
    private function monthlyCostSeries(array $byMonth, CarbonImmutable $from, CarbonImmutable $to): array {
        if ($byMonth === [] || array_sum($byMonth) <= 0) {
            return [];
        }

        $series = [];
        foreach ($this->buildMonthsInRange($from, $to) as $month) {
            $series[] = ['x' => $month['shortLabel'], 'y' => round($byMonth[$month['key']] ?? 0.0, 2)];
        }

        return $series;
    }

    /**
     * @param  array{rows: array<int, array{material_id:int|null, sku:string|null, name:string, unit:string, quantity:float, line_total_net:float, usage_count:int}>, totals: array{materials:int, usage_count:int, line_total_net:float}, monthly: array<string, float>}  $agg
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv(array $agg, string $from, string $to, Request $request, array $exportFilters): Response {
        $filename = sprintf('materialien_%s_%s.csv', $from, $to);
        $rows = [];
        $rows[] = ['SKU', 'Material', 'Einheit', 'Menge', 'Verwendungen', 'Netto €'];
        foreach ($agg['rows'] as $r) {
            $rows[] = [
                $r['sku'] ?? '',
                $r['name'],
                $r['unit'],
                NumberHelper::toUSFormat($r['quantity'], 3),
                $r['usage_count'],
                NumberHelper::toUSFormat($r['line_total_net'], 2),
            ];
        }
        $rows[] = ['', 'GESAMT', '', '', $agg['totals']['usage_count'], NumberHelper::toUSFormat($agg['totals']['line_total_net'], 2)];

        return $this->csvWithMetadata($rows, $filename, 'materials', $exportFilters, $request);
    }

    /**
     * @param  array{rows: array<int, array{material_id:int|null, sku:string|null, name:string, unit:string, quantity:float, line_total_net:float, usage_count:int}>, totals: array{materials:int, usage_count:int, line_total_net:float}, monthly: array<string, float>}  $agg
     * @param  list<array{x: string, y: float}>  $paretoSeries
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportPdf(array $agg, string $from, string $to, string $scope, array $paretoSeries, Request $request, array $exportFilters): SymfonyResponse {
        $filename = sprintf('materialien_%s_%s.pdf', $from, $to);
        return $this->pdfDownload('reports.pdf.materials', [
            'rows' => $agg['rows'],
            'totals' => $agg['totals'],
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Verbrauchswert je Material (Top 20)'),
                'unit' => '€',
                'xLabel' => __('Material'),
                'yLabel' => __('Netto (€)'),
                'series' => $paretoSeries,
            ],
        ], $filename, request: $request, reportCode: 'materials', filters: $exportFilters);
    }
}
