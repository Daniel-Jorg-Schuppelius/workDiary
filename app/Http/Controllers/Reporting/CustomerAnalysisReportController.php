<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerAnalysisReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{Customer, Project, User};
use App\Services\Reporting\{CustomerAnalysisReportBuilder, ReportFilters};
use App\Support\{CarbonFmt, Sqid};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CustomerAnalysisReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function __construct(private readonly CustomerAnalysisReportBuilder $builder) {
    }

    public function index(Request $request): View|Response|SymfonyResponse {
        [$from, $to] = $this->resolveRange($request);

        $minMinutes = max(0, (int) $request->integer('min_minutes', 0));
        $hideZero = $request->boolean('hide_zero');
        $filterFields = ['project', 'user', 'entry_type', 'include_excluded'];
        $filters = $this->standardFilters($request, $filterFields, $from, $to);
        // Legacy-Parameter (project_id/user_id — alte Bookmarks) ins Standard-Set
        // übernehmen, damit Partial, Links und Audit denselben Stand sehen.
        $projectId = $filters->projectId ?? Sqid::decodeOrNumeric(Project::class, $request->query('project_id'));
        $userId = $filters->userId ?? Sqid::decodeOrNumeric(User::class, $request->query('user_id'));
        if ($projectId !== $filters->projectId || $userId !== $filters->userId) {
            $filters = new ReportFilters(
                from: $from,
                to: $to,
                projectId: $projectId,
                userId: $userId,
                entryTypeId: $filters->entryTypeId,
                excludedCustomerIds: $filters->excludedCustomerIds,
                includeExcludedCustomers: $filters->includeExcludedCustomers,
            );
        }

        // Feature 002: Ausblendung greift nur ohne explizite Kunden-/Projektwahl
        // (gleiche Übersteuerungsregel wie ReportFilters::customerExclusionActive()).
        $excludedCustomerIds = $filters->customerId === null && $filters->projectId === null
            ? $filters->excludedCustomerIds
            : [];

        $rows = collect($this->builder->build($from, $to, $projectId, $userId, $filters->entryTypeId, $excludedCustomerIds))
            ->filter(static fn(array $row): bool => $row['totalMinutes'] >= $minMinutes)
            ->when($hideZero, fn($c) => $c->filter(static fn(array $row): bool => $row['entryCount'] > 0
                || $row['totalMinutes'] > 0
                || $row['reworkEntryCount'] > 0
                || $row['openIssueCount'] > 0
                || $row['escalationCount'] > 0))
            ->values();

        $exportFilters = array_merge(['min_minutes' => $minMinutes, 'hide_zero' => $hideZero], $filters->toAuditArray());
        $label = CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to);

        if ($request->query('export') === 'csv') {
            return $this->exportCsv(array_values($rows->all()), $from->toDateString(), $to->toDateString(), $exportFilters, $request);
        }

        if ($request->query('export') === 'pdf') {
            return $this->exportPdf(
                array_values($rows->all()),
                $label,
                $from->toDateString(),
                $to->toDateString(),
                $this->customerHoursSeries(array_values($rows->all()), $filters),
                $exportFilters,
                $request,
            );
        }

        $topByMinutes = $rows->sortByDesc('totalMinutes')->take(5)->values();
        $topByRework = $rows->sortByDesc('reworkEntryCount')->take(5)->values();
        $topByNonBillable = $rows->sortByDesc('nonBillableMinutes')->take(5)->values();

        return view('reports.customers', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'minMinutes' => $minMinutes,
            'hideZero' => $hideZero,
            'projectId' => $projectId,
            'userId' => $userId,
            'topByMinutes' => $topByMinutes,
            'topByRework' => $topByRework,
            'topByNonBillable' => $topByNonBillable,
            'standardFilters' => $filters,
            'filterFields' => $filterFields,
            'customerHoursSeries' => $this->customerHoursSeries(array_values($rows->all()), $filters),
            'trendSeries' => $this->trendSeries($from, $to, $filters, $excludedCustomerIds),
            'openIssuesSeries' => $this->openIssuesSeries(array_values($rows->all()), $filters),
            'periodPhrase' => $this->periodPhrase($this->bucketGranularity($from, $to)),
            'periodAxis' => $this->periodAxisLabel($this->bucketGranularity($from, $to)),
            ...$this->standardFilterOptions($filterFields, $filters),
        ]);
    }

    /**
     * Stunden je Kunde (Top 20) — Pareto am Screen, bar-h im PDF; Drilldown
     * öffnet den Kunden im Kunden-&-Projekte-Report mit geerbtem Filterkontext.
     *
     * @param  array<int, array{customerId:int, customerName:string, entryCount:int, totalMinutes:int, billableMinutes:int, nonBillableMinutes:int, nonBillableShare:float, reworkEntryCount:int, openIssueCount:int, escalationCount:int, avgEntryMinutes:int, trend30d:int}>  $rows
     * @return list<array{x: string, y: float, url: string}>
     */
    private function customerHoursSeries(array $rows, ReportFilters $filters): array {
        return array_values(collect($rows)
            ->filter(static fn(array $row): bool => $row['totalMinutes'] > 0)
            ->sortByDesc('totalMinutes')
            ->take(20)
            ->map(fn(array $row): array => [
                'x' => $row['customerName'],
                'y' => round($row['totalMinutes'] / 60, 1),
                'url' => route('reports.customer-project', array_merge($filters->toQueryParams(), [
                    'customer' => Sqid::encode(Customer::class, $row['customerId']),
                ])),
            ])
            ->all());
    }

    /**
     * Auftragseingang je Zeit-Bucket im gewählten Zeitraum (adaptive
     * Header-Granularität), als Trend-Linienchart.
     *
     * @param  list<int>  $excludedCustomerIds
     * @return list<array{x: string, y: int}>
     */
    private function trendSeries(\Carbon\CarbonImmutable $from, \Carbon\CarbonImmutable $to, ReportFilters $filters, array $excludedCustomerIds = []): array {
        $granularity = $this->bucketGranularity($from, $to);
        $series = $this->builder->entrySeries($from, $to, $granularity, $filters->projectId, $filters->userId, $filters->entryTypeId, $excludedCustomerIds);
        if (array_sum(array_column($series, 'count')) === 0) {
            return []; // Leerzustand statt Null-Linie (§Diagramm-UX).
        }

        return array_map(static fn(array $point): array => [
            'x' => $point['label'],
            'y' => $point['count'],
        ], $series);
    }

    /**
     * Offene Punkte je Kunde (Top 15) — Drilldown in die Offene-Punkte-Liste
     * (Drilldown-Controller erwartet die Legacy-Parameternamen customer_id/…).
     *
     * @param  array<int, array{customerId:int, customerName:string, entryCount:int, totalMinutes:int, billableMinutes:int, nonBillableMinutes:int, nonBillableShare:float, reworkEntryCount:int, openIssueCount:int, escalationCount:int, avgEntryMinutes:int, trend30d:int}>  $rows
     * @return list<array{x: string, y: int, url: string}>
     */
    private function openIssuesSeries(array $rows, ReportFilters $filters): array {
        return array_values(collect($rows)
            ->filter(static fn(array $row): bool => $row['openIssueCount'] > 0)
            ->sortByDesc('openIssueCount')
            ->take(15)
            ->map(fn(array $row): array => [
                'x' => $row['customerName'],
                'y' => $row['openIssueCount'],
                'url' => route('reports.customers.drilldown.open-issues', array_filter([
                    'customer_id' => Sqid::encode(Customer::class, $row['customerId']),
                    'project_id' => Sqid::encode(Project::class, $filters->projectId),
                    'user_id' => Sqid::encode(User::class, $filters->userId),
                ])),
            ])
            ->all());
    }

    /**
     * @param  array<int, array{
     *   customerId:int,
     *   customerName:string,
     *   entryCount:int,
     *   totalMinutes:int,
     *   billableMinutes:int,
     *   nonBillableMinutes:int,
     *   nonBillableShare:float,
     *   reworkEntryCount:int,
     *   openIssueCount:int,
     *   escalationCount:int,
     *   avgEntryMinutes:int,
     *   trend30d:int
     * }>             $rows
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $rows, string $from, string $to, array $filters, Request $request): Response {
        $filename = sprintf('kundenanalyse_%s_%s.csv', $from, $to);
        $out = [];
        $out[] = [
            'Kunde',
            'Auftraege',
            'GesamtMinuten',
            'AbrechenbarMinuten',
            'NichtAbrechenbarMinuten',
            'NichtAbrechenbarAnteilProzent',
            'Nacharbeit',
            'OffenePunkte',
            'Eskaliert',
            'DurchschnittMinutenProAuftrag',
            'Trend30d',
        ];

        foreach ($rows as $row) {
            $out[] = [
                $row['customerName'],
                $row['entryCount'],
                $row['totalMinutes'],
                $row['billableMinutes'],
                $row['nonBillableMinutes'],
                NumberHelper::toUSFormat((float) $row['nonBillableShare'], 2),
                $row['reworkEntryCount'],
                $row['openIssueCount'],
                $row['escalationCount'],
                $row['avgEntryMinutes'],
                $row['trend30d'],
            ];
        }

        return $this->csvWithMetadata($out, $filename, 'customers-analysis', $filters, $request);
    }

    /**
     * @param  array<int, array{
     *   customerId:int,
     *   customerName:string,
     *   entryCount:int,
     *   totalMinutes:int,
     *   billableMinutes:int,
     *   nonBillableMinutes:int,
     *   nonBillableShare:float,
     *   reworkEntryCount:int,
     *   openIssueCount:int,
     *   escalationCount:int,
     *   avgEntryMinutes:int,
     *   trend30d:int
     * }>  $rows
     * @param  list<array{x: string, y: float, url: string}>  $hoursSeries
     * @param  array<string, mixed>  $filters
     */
    private function exportPdf(array $rows, string $label, string $from, string $to, array $hoursSeries, array $filters, Request $request): SymfonyResponse {
        $filename = sprintf('kundenanalyse_%s_%s.pdf', $from, $to);

        return $this->pdfDownload('reports.pdf.customers', [
            'rows' => $rows,
            'label' => $label,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Stunden je Kunde (Top 20)'),
                'unit' => 'h',
                'xLabel' => __('Kunde'),
                'yLabel' => __('Stunden'),
                'series' => array_values(array_filter($hoursSeries, static fn(array $point): bool => $point['y'] > 0)),
            ],
        ], $filename, 'landscape', $request, 'customers-analysis', $filters);
    }
}
