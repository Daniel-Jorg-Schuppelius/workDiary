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
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, WritesReportCsv};
use App\Models\MaterialUsage;
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
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        [$scope, $isAdmin] = $this->resolveScopeWithAdmin($request);

        [$fromDate, $toDate] = $this->globalDateRangeBounds();
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $aggregation = $this->aggregate($from, $to, $scope, $userId);

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($aggregation, $from, $to, $scope, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($aggregation, $from, $to, $scope, $request);
        }

        return view('reports.materials', [
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'rows' => $aggregation['rows'],
            'totals' => $aggregation['totals'],
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
     *   totals: array{materials:int, usage_count:int, line_total_net:float}
     * }
     */
    private function aggregate(string $from, string $to, string $scope, int $userId): array {
        $q = MaterialUsage::query()
            ->with(['material:id,sku,name,unit'])
            ->whereHas('timesheet', function ($w) use ($from, $to, $scope, $userId): void {
                $w->whereBetween('work_date', [$from, $to]);
                if ($scope === 'mine') {
                    $w->where('user_id', $userId);
                }
            });

        /** @var Collection<int, MaterialUsage> $usages */
        $usages = $q->get(['id', 'material_id', 'timesheet_id', 'description', 'quantity', 'unit', 'unit_price', 'line_total_net']);

        /** @var array<string, array{material_id: int|null, sku: string|null, name: string, unit: string, quantity: float, line_total_net: float, usage_count: int}> $byKey */
        $byKey = [];
        $sumNet = 0.0;
        foreach ($usages as $u) {
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
        ];
    }

    /**
     * @param  array{rows: array<int, array{material_id:int|null, sku:string|null, name:string, unit:string, quantity:float, line_total_net:float, usage_count:int}>, totals: array{materials:int, usage_count:int, line_total_net:float}}  $agg
     */
    private function exportCsv(array $agg, string $from, string $to, string $scope, Request $request): Response {
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

        return $this->csvWithMetadata($rows, $filename, 'materials', [
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
        ], $request);
    }

    /**
     * @param  array{rows: array<int, array{material_id:int|null, sku:string|null, name:string, unit:string, quantity:float, line_total_net:float, usage_count:int}>, totals: array{materials:int, usage_count:int, line_total_net:float}}  $agg
     */
    private function exportPdf(array $agg, string $from, string $to, string $scope, Request $request): SymfonyResponse {
        $filename = sprintf('materialien_%s_%s.pdf', $from, $to);
        return $this->pdfDownload('reports.pdf.materials', [
            'rows' => $agg['rows'],
            'totals' => $agg['totals'],
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
        ], $filename, request: $request, reportCode: 'materials', filters: ['from' => $from, 'to' => $to, 'scope' => $scope]);
    }
}
