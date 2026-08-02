<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerValueReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{Customer, User};
use App\Services\Reporting\{CustomerValueReportBuilder, ReportFilters};
use App\Support\{CarbonFmt, Sqid};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Kundenwert & Portfolio (MVP-465, Feature 002): Von welchen Kunden lebt
 * das Unternehmen (Konzentration), welche A-Kunden sind gefährdet (RFM),
 * wo liegt Ausbaupotenzial?
 */
class CustomerValueReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function __construct(private readonly CustomerValueReportBuilder $builder) {}

    public function index(Request $request): View|Response|SymfonyResponse {
        $authUser = Auth::user();
        $allowed = $authUser instanceof User
            && ($authUser->isAdmin() || $authUser->can(Permission::ReportView->value));
        abort_unless($allowed, 403);

        [$from, $to] = $this->resolveRange($request);
        $label = CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to);

        $riskDays = max(1, (int) $request->integer('risk_days', 60));
        // Segment-Drilldown (MVP-470): filtert NUR die Kundenliste, nicht
        // die Charts/KPIs — sonst würde die Konzentrationssicht kippen.
        $segment = $request->query('segment');
        $segment = is_string($segment) && array_key_exists($segment, $this->segmentLabels()) ? $segment : null;
        $filterFields = ['project', 'user', 'include_excluded'];
        $filters = $this->standardFilters($request, $filterFields, $from, $to);

        // Feature 002: Ausblendung greift nur ohne explizite Projektwahl
        // (gleiche Übersteuerungsregel wie ReportFilters::customerExclusionActive()).
        $excludedCustomerIds = $filters->customerId === null && $filters->projectId === null
            ? $filters->excludedCustomerIds
            : [];

        $result = $this->builder->build($from, $to, $filters->projectId, $filters->userId, $excludedCustomerIds);
        $rows = collect($result['rows']);
        $riskRows = $this->builder->riskRows($result['rows'], $riskDays);
        $riskSparklines = $this->builder->monthlyRevenueSeries(
            array_map(static fn(array $row): int => $row['customerId'], $riskRows),
            $to,
        );

        $exportFilters = array_merge(['risk_days' => $riskDays], $filters->toAuditArray());

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($result['rows'], $from->toDateString(), $to->toDateString(), $exportFilters, $request);
        }

        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($result, $label, $from->toDateString(), $to->toDateString(), $this->revenueSeries($result['rows'], $filters), $exportFilters, $request);
        }

        return view('reports.customer-value', [
            'rows' => $rows,
            'tableRows' => $segment !== null ? $rows->where('segment', $segment)->values() : $rows,
            'segment' => $segment,
            'segments' => $result['segments'],
            'segmentLabels' => $this->segmentLabels(),
            'concentration' => $result['concentration'],
            'riskRows' => $riskRows,
            'riskSparklines' => $riskSparklines,
            'riskDays' => $riskDays,
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'standardFilters' => $filters,
            'filterFields' => $filterFields,
            'revenueSeries' => $this->revenueSeries($result['rows'], $filters),
            'riskScatter' => $this->riskScatter($result['rows'], $filters),
            'segmentSeries' => $this->segmentSeries($result['segments'], $filters, $riskDays),
            ...$this->standardFilterOptions($filterFields, $filters),
        ]);
    }

    /** @return array<string, string> Segment-Schlüssel → Anzeigename. */
    private function segmentLabels(): array {
        return [
            'champion' => (string) __('Champions'),
            'loyal' => (string) __('Stammkunden'),
            'potential' => (string) __('Ausbaufähig'),
            'new' => (string) __('Neu'),
            'at_risk' => (string) __('Gefährdet'),
            'inactive' => (string) __('Inaktiv'),
        ];
    }

    /**
     * Erlös je Kunde (Top 20) — Pareto am Screen, bar-h im PDF; Drilldown
     * in den Kunden-&-Projekte-Report mit geerbtem Filterkontext.
     *
     * @param  list<array{customerId:int, customerName:string, recencyDays:?int, frequencyDays:int, revenue:float, invoiced:float, totalMinutes:int, r:?int, f:?int, m:?int, segment:string, firstActivity:?string, lastActivity:?string}>  $rows
     * @return list<array{x: string, y: float, url: string}>
     */
    private function revenueSeries(array $rows, ReportFilters $filters): array {
        return array_values(collect($rows)
            ->filter(static fn(array $row): bool => $row['revenue'] > 0)
            ->sortByDesc('revenue')
            ->take(20)
            ->map(fn(array $row): array => [
                'x' => $row['customerName'],
                'y' => round($row['revenue'], 2),
                'url' => route('reports.customer-project', array_merge($filters->toQueryParams(), [
                    'customer' => Sqid::encode(Customer::class, $row['customerId']),
                ])),
            ])
            ->all());
    }

    /**
     * Erlös je Kunde, geordnet nach Tagen seit letzter Leistung — Punkte
     * rechts oberhalb der P80-Linie sind gefährdete A-Kunden.
     *
     * @param  list<array{customerId:int, customerName:string, recencyDays:?int, frequencyDays:int, revenue:float, invoiced:float, totalMinutes:int, r:?int, f:?int, m:?int, segment:string, firstActivity:?string, lastActivity:?string}>  $rows
     * @return array{series: list<array{x: string, y: float, url: string}>, percentiles: array<string, float>}
     */
    private function riskScatter(array $rows, ReportFilters $filters): array {
        $active = collect($rows)
            ->filter(static fn(array $row): bool => $row['revenue'] > 0 && $row['recencyDays'] !== null)
            ->sortBy('recencyDays')
            ->values();

        $series = $active
            ->map(fn(array $row): array => [
                'x' => $row['customerName'] . ' (' . $row['recencyDays'] . ' ' . __('Tage') . ')',
                'y' => round($row['revenue'], 2),
                'url' => route('reports.customer-project', array_merge($filters->toQueryParams(), [
                    'customer' => Sqid::encode(Customer::class, $row['customerId']),
                ])),
            ])
            ->all();

        $percentiles = [];
        if ($active->count() >= 5) {
            $sorted = $active->pluck('revenue')->sort()->values();
            $idx = (int) floor($sorted->count() * 0.8);
            $percentiles['P80'] = round((float) $sorted->get(min($idx, $sorted->count() - 1)), 2);
        }

        return ['series' => array_values($series), 'percentiles' => $percentiles];
    }

    /**
     * Segmentverteilung mit Drilldown: Klick filtert die Kundenliste der
     * Seite auf das Segment (Anker #kundenliste, MVP-470).
     *
     * @param  array<string, int>  $segments
     * @return list<array{x: string, y: int, url: string}>
     */
    private function segmentSeries(array $segments, ReportFilters $filters, int $riskDays): array {
        $labels = $this->segmentLabels();
        $baseParams = array_merge(
            $filters->toQueryParams(),
            $riskDays !== 60 ? ['risk_days' => $riskDays] : [],
        );

        return array_values(collect($segments)
            ->map(static fn(int $count, string $key): array => ['key' => $key, 'count' => $count])
            ->values()
            ->filter(static fn(array $row): bool => $row['count'] > 0)
            ->map(static fn(array $row): array => [
                'x' => $labels[$row['key']] ?? $row['key'],
                'y' => $row['count'],
                'url' => route('reports.customer-value', array_merge($baseParams, ['segment' => $row['key']])) . '#kundenliste',
            ])
            ->all());
    }

    /**
     * @param  list<array{customerId:int, customerName:string, recencyDays:?int, frequencyDays:int, revenue:float, invoiced:float, totalMinutes:int, r:?int, f:?int, m:?int, segment:string, firstActivity:?string, lastActivity:?string}>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $rows, string $from, string $to, array $filters, Request $request): Response {
        $filename = sprintf('kundenwert_%s_%s.csv', $from, $to);
        $labels = $this->segmentLabels();
        $out = [];
        $out[] = [
            'Kunde',
            'Segment',
            'TageSeitLetzterLeistung',
            'Aktivitaetstage',
            'ErloesEUR',
            'FakturiertEUR',
            'GesamtMinuten',
            'R',
            'F',
            'M',
            'ErsteLeistung',
            'LetzteLeistung',
        ];

        foreach ($rows as $row) {
            $out[] = [
                $row['customerName'],
                $labels[$row['segment']] ?? $row['segment'],
                $row['recencyDays'] ?? '',
                $row['frequencyDays'],
                NumberHelper::toUSFormat($row['revenue'], 2),
                NumberHelper::toUSFormat($row['invoiced'], 2),
                $row['totalMinutes'],
                $row['r'] ?? '',
                $row['f'] ?? '',
                $row['m'] ?? '',
                $row['firstActivity'] ?? '',
                $row['lastActivity'] ?? '',
            ];
        }

        return $this->csvWithMetadata($out, $filename, 'customer-value', $filters, $request);
    }

    /**
     * @param  array{rows: list<array{customerId:int, customerName:string, recencyDays:?int, frequencyDays:int, revenue:float, invoiced:float, totalMinutes:int, r:?int, f:?int, m:?int, segment:string, firstActivity:?string, lastActivity:?string}>, segments: array<string, int>, concentration: array{totalRevenue:float, top5Share:?float, top10Share:?float, hhi:?int, activeCustomers:int}}  $result
     * @param  list<array{x: string, y: float, url: string}>  $revenueSeries
     * @param  array<string, mixed>  $filters
     */
    private function exportPdf(array $result, string $label, string $from, string $to, array $revenueSeries, array $filters, Request $request): SymfonyResponse {
        $filename = sprintf('kundenwert_%s_%s.pdf', $from, $to);

        return $this->pdfDownload('reports.pdf.customer-value', [
            'rows' => $result['rows'],
            'segments' => $result['segments'],
            'segmentLabels' => $this->segmentLabels(),
            'concentration' => $result['concentration'],
            'label' => $label,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Erlös je Kunde (Top 20)'),
                'unit' => '€',
                'xLabel' => __('Kunde'),
                'yLabel' => '€',
                'series' => $revenueSeries,
            ],
        ], $filename, 'landscape', $request, 'customer-value', $filters);
    }
}
