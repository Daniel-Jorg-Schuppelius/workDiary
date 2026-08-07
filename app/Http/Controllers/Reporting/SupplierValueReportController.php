<?php
/*
 * Created on   : Fri Aug 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierValueReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\{Supplier, User};
use App\Services\Reporting\SupplierValueReportBuilder;
use App\Support\{CarbonFmt, Sqid};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Lieferantenwert & Portfolio (Feature 002, MVP-473): Von welchen Lieferanten
 * hängen wir ab (Konzentration), welche A-Lieferanten sind Klumpenrisiko,
 * welche sind strategisch, ruhend oder sporadisch? Einkaufs-Pendant zum
 * Kundenwert. Recht: report.view/Admin (Ausgaben = Finanzdaten).
 */
class SupplierValueReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function __construct(private readonly SupplierValueReportBuilder $builder) {
    }

    public function index(Request $request): View|Response|SymfonyResponse {
        $authUser = Auth::user();
        $allowed = $authUser instanceof User
            && ($authUser->isAdmin() || $authUser->can(Permission::ReportView->value));
        abort_unless($allowed, 403);

        [$from, $to] = $this->resolveRange($request);
        $label = CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to);

        $riskShare = max(1.0, (float) $request->float('risk_share', 15.0));
        // Segment-Drilldown: filtert NUR die Lieferantenliste, nicht Charts/KPIs.
        $segment = $request->query('segment');
        $segment = is_string($segment) && array_key_exists($segment, $this->segmentLabels()) ? $segment : null;

        $result = $this->builder->build($from, $to);
        $rows = collect($result['rows']);
        $riskRows = $this->builder->riskRows($result['rows'], $riskShare);
        $riskSparklines = $this->builder->monthlySpendSeries(
            array_map(static fn(array $row): int => $row['supplierId'], $riskRows),
            $to,
        );

        $exportFilters = ['risk_share' => $riskShare];

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($result['rows'], $from->toDateString(), $to->toDateString(), $exportFilters, $request);
        }

        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($result, $label, $from->toDateString(), $to->toDateString(), $this->spendSeries($result['rows']), $exportFilters, $request);
        }

        return view('reports.supplier-value', [
            'rows' => $rows,
            'tableRows' => $segment !== null ? $rows->where('segment', $segment)->values() : $rows,
            'segment' => $segment,
            'segments' => $result['segments'],
            'segmentLabels' => $this->segmentLabels(),
            'concentration' => $result['concentration'],
            'riskRows' => $riskRows,
            'riskSparklines' => $riskSparklines,
            'riskShare' => $riskShare,
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'spendSeries' => $this->spendSeries($result['rows']),
            'dependencyScatter' => $this->dependencyScatter($result['rows']),
            'segmentSeries' => $this->segmentSeries($result['segments'], $riskShare),
        ]);
    }

    /** @return array<string, string> Segment-Schlüssel → Anzeigename. */
    private function segmentLabels(): array {
        return [
            'strategic' => (string) __('Strategisch'),
            'core' => (string) __('Stammlieferant'),
            'occasional' => (string) __('Sporadisch'),
            'new' => (string) __('Neu'),
            'lapsed' => (string) __('Ruhender Schlüssellieferant'),
            'dormant' => (string) __('Ruhend'),
        ];
    }

    /**
     * Ausgaben je Lieferant (Top 20) — Pareto am Screen, bar-h im PDF;
     * Drilldown auf die Lieferanten-Detailseite.
     *
     * @param  list<array{supplierId:int, supplierName:string, recencyDays:?int, frequencyDays:int, spend:float, voucherCount:int, spendShare:float, r:?int, f:?int, m:?int, segment:string, firstActivity:?string, lastActivity:?string}>  $rows
     * @return list<array{x: string, y: float, url: string}>
     */
    private function spendSeries(array $rows): array {
        return array_values(collect($rows)
            ->filter(static fn(array $row): bool => $row['spend'] > 0)
            ->sortByDesc('spend')
            ->take(20)
            ->map(static fn(array $row): array => [
                'x' => $row['supplierName'],
                'y' => round($row['spend'], 2),
                'url' => route('suppliers.show', Sqid::encode(Supplier::class, $row['supplierId'])),
            ])
            ->all());
    }

    /**
     * Ausgaben nach Inaktivität (rechts = länger her): Punkte rechts oben =
     * umsatzstarke Lieferanten, die lange nichts geliefert haben.
     *
     * @param  list<array{supplierId:int, supplierName:string, recencyDays:?int, frequencyDays:int, spend:float, voucherCount:int, spendShare:float, r:?int, f:?int, m:?int, segment:string, firstActivity:?string, lastActivity:?string}>  $rows
     * @return array{series: list<array{x: string, y: float, url: string}>, percentiles: array<string, float>}
     */
    private function dependencyScatter(array $rows): array {
        $active = collect($rows)
            ->filter(static fn(array $row): bool => $row['spend'] > 0 && $row['recencyDays'] !== null)
            ->sortBy('recencyDays')
            ->values();

        $series = $active
            ->map(static fn(array $row): array => [
                'x' => $row['supplierName'] . ' (' . $row['recencyDays'] . ' ' . __('Tage') . ')',
                'y' => round($row['spend'], 2),
                'url' => route('suppliers.show', Sqid::encode(Supplier::class, $row['supplierId'])),
            ])
            ->all();

        $percentiles = [];
        if ($active->count() >= 5) {
            $sorted = $active->pluck('spend')->sort()->values();
            $idx = (int) floor($sorted->count() * 0.8);
            $percentiles['P80'] = round((float) $sorted->get(min($idx, $sorted->count() - 1)), 2);
        }

        return ['series' => array_values($series), 'percentiles' => $percentiles];
    }

    /**
     * Segmentverteilung mit Drilldown: Klick filtert die Lieferantenliste auf
     * das Segment (Anker #lieferantenliste).
     *
     * @param  array<string, int>  $segments
     * @return list<array{x: string, y: int, url: string}>
     */
    private function segmentSeries(array $segments, float $riskShare): array {
        $labels = $this->segmentLabels();
        $baseParams = $riskShare !== 15.0 ? ['risk_share' => $riskShare] : [];

        return array_values(collect($segments)
            ->map(static fn(int $count, string $key): array => ['key' => $key, 'count' => $count])
            ->values()
            ->filter(static fn(array $row): bool => $row['count'] > 0)
            ->map(static fn(array $row): array => [
                'x' => $labels[$row['key']] ?? $row['key'],
                'y' => $row['count'],
                'url' => route('reports.supplier-value', array_merge($baseParams, ['segment' => $row['key']])) . '#lieferantenliste',
            ])
            ->all());
    }

    /**
     * @param  list<array{supplierId:int, supplierName:string, recencyDays:?int, frequencyDays:int, spend:float, voucherCount:int, spendShare:float, r:?int, f:?int, m:?int, segment:string, firstActivity:?string, lastActivity:?string}>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $rows, string $from, string $to, array $filters, Request $request): Response {
        $filename = sprintf('lieferantenwert_%s_%s.csv', $from, $to);
        $labels = $this->segmentLabels();
        $out = [];
        $out[] = [
            'Lieferant',
            'Segment',
            'TageSeitLetztemBeleg',
            'Belegtage',
            'AusgabenEUR',
            'Belege',
            'AusgabenanteilProzent',
            'R',
            'F',
            'M',
            'ErsterBeleg',
            'LetzterBeleg',
        ];

        foreach ($rows as $row) {
            $out[] = [
                $row['supplierName'],
                $labels[$row['segment']] ?? $row['segment'],
                $row['recencyDays'] ?? '',
                $row['frequencyDays'],
                NumberHelper::toUSFormat($row['spend'], 2),
                $row['voucherCount'],
                NumberHelper::toUSFormat($row['spendShare'], 1),
                $row['r'] ?? '',
                $row['f'] ?? '',
                $row['m'] ?? '',
                $row['firstActivity'] ?? '',
                $row['lastActivity'] ?? '',
            ];
        }

        return $this->csvWithMetadata($out, $filename, 'supplier-value', $filters, $request);
    }

    /**
     * @param  array{rows: list<array{supplierId:int, supplierName:string, recencyDays:?int, frequencyDays:int, spend:float, voucherCount:int, spendShare:float, r:?int, f:?int, m:?int, segment:string, firstActivity:?string, lastActivity:?string}>, segments: array<string, int>, concentration: array{totalSpend:float, top5Share:?float, top10Share:?float, hhi:?int, activeSuppliers:int}}  $result
     * @param  list<array{x: string, y: float, url: string}>  $spendSeries
     * @param  array<string, mixed>  $filters
     */
    private function exportPdf(array $result, string $label, string $from, string $to, array $spendSeries, array $filters, Request $request): SymfonyResponse {
        $filename = sprintf('lieferantenwert_%s_%s.pdf', $from, $to);

        return $this->pdfDownload('reports.pdf.supplier-value', [
            'rows' => $result['rows'],
            'segments' => $result['segments'],
            'segmentLabels' => $this->segmentLabels(),
            'concentration' => $result['concentration'],
            'label' => $label,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Ausgaben je Lieferant (Top 20)'),
                'unit' => '€',
                'xLabel' => __('Lieferant'),
                'yLabel' => '€',
                'series' => $spendSeries,
            ],
        ], $filename, 'landscape', $request, 'supplier-value', $filters);
    }
}
