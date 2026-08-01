<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntryTypeAnalysisReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Diary\Status as DiaryStatus;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{Customer, EntryType, User};
use App\Services\Reporting\{EntryTypeAnalysisReportBuilder, ReportFilters};
use App\Support\{CarbonFmt, Sqid};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EntryTypeAnalysisReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function __construct(private readonly EntryTypeAnalysisReportBuilder $builder) {}

    public function index(Request $request): View|Response|SymfonyResponse {
        [$from, $to] = $this->resolveRange($request);
        $label = CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to);

        $statusValues = array_map(static fn(DiaryStatus $status): string => (string) $status->value, DiaryStatus::cases());
        $filterFields = ['customer', 'project', 'user', 'status', 'include_excluded'];
        $filters = $this->standardFilters($request, $filterFields, $from, $to, $statusValues);
        // Legacy-Parameter (customer_id/user_id — alte Bookmarks) ins
        // Standard-Set übernehmen, damit Partial, Links und Audit denselben
        // Stand sehen. entry_type_id bleibt bewusst eigener Drilldown-Param —
        // der Report gliedert nach Typ, entry_type gehört nicht ins Set.
        $customerId = $filters->customerId ?? Sqid::decodeOrNumeric(Customer::class, $request->query('customer_id'));
        $userId = $filters->userId ?? Sqid::decodeOrNumeric(User::class, $request->query('user_id'));
        if ($customerId !== $filters->customerId || $userId !== $filters->userId) {
            $filters = new ReportFilters(
                from: $from,
                to: $to,
                customerId: $customerId,
                projectId: $filters->projectId,
                userId: $userId,
                status: $filters->status,
                excludedCustomerIds: $filters->excludedCustomerIds,
                includeExcludedCustomers: $filters->includeExcludedCustomers,
            );
        }

        $entryTypeFilter = Sqid::decodeOrNumeric(EntryType::class, $request->query('entry_type_id'));
        $statusFilter = $filters->status !== null ? (int) $filters->status : null;

        // Feature 002: Ausblendung greift nur ohne explizite Kunden-/Projektwahl
        // (gleiche Übersteuerungsregel wie ReportFilters::customerExclusionActive()).
        $excludedCustomerIds = $customerId === null && $filters->projectId === null
            ? $filters->excludedCustomerIds
            : [];

        $rows = $this->builder->build($from, $to, $customerId, $userId, $entryTypeFilter, $statusFilter, $filters->projectId, $excludedCustomerIds);

        $exportContext = array_merge(['entry_type_id' => $entryTypeFilter], $filters->toAuditArray());

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($rows, $from->toDateString(), $to->toDateString(), $exportContext, $request);
        }

        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($rows, $label, $from->toDateString(), $to->toDateString(), $exportContext, $request);
        }

        return view('reports.entry-types', [
            'rows' => $rows,
            'label' => $label,
            'from' => $from,
            'to' => $to,
            'entryTypes' => EntryType::query()->ordered()->get(['id', 'label']),
            'customerId' => $customerId,
            'userId' => $userId,
            'entryTypeFilter' => $entryTypeFilter,
            'statusFilter' => $statusFilter,
            'standardFilters' => $filters,
            'filterFields' => $filterFields,
            'planVsIstSeries' => $this->planVsIstSeries($rows, $filters),
            'overrunSeries' => $this->overrunSeries($rows, $filters),
            ...$this->standardFilterOptions(['customer', 'project', 'user', 'include_excluded'], $filters),
        ]);
    }

    /**
     * Ø Ist vs. Ø Plan (Minuten) je Auftragstyp — Zweitserie (y2) = Plan;
     * Drilldown öffnet die Auftragsliste des Typs mit geerbtem Filterkontext.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{x: string, y: float, y2: float, url: string}>
     */
    private function planVsIstSeries(array $rows, ReportFilters $filters): array {
        return array_values(collect($rows)
            ->filter(static fn(array $row): bool => (float) $row['avgActualMinutes'] > 0.0 || (float) $row['avgPlannedMinutes'] > 0.0)
            ->map(fn(array $row): array => [
                'x' => (string) $row['entryTypeName'],
                'y' => (float) $row['avgActualMinutes'],
                'y2' => (float) $row['avgPlannedMinutes'],
                'url' => $this->entryTypeDrilldownUrl((int) $row['entryTypeId'], $filters),
            ])
            ->all());
    }

    /**
     * Überzugsquote (%) je Auftragstyp, Top 15 — Überzüge haben keinen
     * eigenen Drilldown-Endpunkt, der passende bestehende ist die
     * Auftragsliste des Typs (wie die Typ-Spalte der Tabelle).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{x: string, y: float, url: string}>
     */
    private function overrunSeries(array $rows, ReportFilters $filters): array {
        return array_values(collect($rows)
            ->filter(static fn(array $row): bool => (float) $row['overrunShare'] > 0.0)
            ->sortByDesc('overrunShare')
            ->take(15)
            ->map(fn(array $row): array => [
                'x' => (string) $row['entryTypeName'],
                'y' => (float) $row['overrunShare'],
                'url' => $this->entryTypeDrilldownUrl((int) $row['entryTypeId'], $filters),
            ])
            ->all());
    }

    /** Auftragslisten-Drilldown eines Typs mit geerbtem Filterkontext. */
    private function entryTypeDrilldownUrl(int $entryTypeId, ReportFilters $filters): string {
        return route('diary.index', array_filter([
            'from' => $filters->from->toDateString(),
            'to' => $filters->to->toDateString(),
            'customer' => Sqid::encode(Customer::class, $filters->customerId),
            'entry_type' => $entryTypeId > 0 ? Sqid::encode(EntryType::class, $entryTypeId) : null,
            'status' => $filters->status,
        ]));
    }

    /**
     * @param  list<array{
     *   entryTypeId:int,
     *   entryTypeName:string,
     *   entryCount:int,
     *   avgPlannedMinutes:float,
     *   avgActualMinutes:float,
     *   planActualRatio:float|null,
     *   overrunCount:int,
     *   overrunShare:float,
     *   reworkCount:int,
     *   reworkShare:float,
     *   escalationCount:int,
     *   escalationShare:float,
     *   firstTimeRightShare:float,
     *   medianActualMinutes:float,
     *   p90ActualMinutes:float,
     *   revenue:float,
     *   cost:float,
     *   contribution:float,
     *   contributionPerEntry:float
     * }>             $rows
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $rows, string $from, string $to, array $filters, Request $request): Response {
        $filename = sprintf('auftragstypanalyse_%s_%s.csv', $from, $to);

        $out = [];
        $out[] = [
            'Auftragstyp',
            'Auftraege',
            'DurchschnittPlanMinuten',
            'DurchschnittIstMinuten',
            'PlanIstVerhaeltnis',
            'UeberzugAnzahl',
            'UeberzugProzent',
            'NacharbeitAnzahl',
            'NacharbeitProzent',
            'EscalationAnzahl',
            'EscalationProzent',
            'FirstTimeRightProzent',
            'MedianIstMinuten',
            'P90IstMinuten',
            'ErloesEUR',
            'KostenEUR',
            'DeckungsbeitragEUR',
            'DBproAuftragEUR',
        ];

        foreach ($rows as $row) {
            $out[] = [
                $row['entryTypeName'],
                $row['entryCount'],
                NumberHelper::toUSFormat((float) $row['avgPlannedMinutes'], 2),
                NumberHelper::toUSFormat((float) $row['avgActualMinutes'], 2),
                $row['planActualRatio'] === null ? '' : NumberHelper::toUSFormat((float) $row['planActualRatio'], 3),
                $row['overrunCount'],
                NumberHelper::toUSFormat((float) $row['overrunShare'], 2),
                $row['reworkCount'],
                NumberHelper::toUSFormat((float) $row['reworkShare'], 2),
                $row['escalationCount'],
                NumberHelper::toUSFormat((float) $row['escalationShare'], 2),
                NumberHelper::toUSFormat((float) $row['firstTimeRightShare'], 2),
                NumberHelper::toUSFormat((float) $row['medianActualMinutes'], 2),
                NumberHelper::toUSFormat((float) $row['p90ActualMinutes'], 2),
                NumberHelper::toUSFormat((float) $row['revenue'], 2),
                NumberHelper::toUSFormat((float) $row['cost'], 2),
                NumberHelper::toUSFormat((float) $row['contribution'], 2),
                NumberHelper::toUSFormat((float) $row['contributionPerEntry'], 2),
            ];
        }

        return $this->csvWithMetadata($out, $filename, 'entry-types-analysis', $filters, $request);
    }

    /**
     * @param  list<array{
     *   entryTypeId:int,
     *   entryTypeName:string,
     *   entryCount:int,
     *   avgPlannedMinutes:float,
     *   avgActualMinutes:float,
     *   planActualRatio:float|null,
     *   overrunCount:int,
     *   overrunShare:float,
     *   reworkCount:int,
     *   reworkShare:float,
     *   escalationCount:int,
     *   escalationShare:float,
     *   firstTimeRightShare:float,
     *   medianActualMinutes:float,
     *   p90ActualMinutes:float
     * }>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function exportPdf(array $rows, string $label, string $from, string $to, array $filters, Request $request): SymfonyResponse {
        $filename = sprintf('auftragstypanalyse_%s_%s.pdf', $from, $to);

        $chartSeries = array_values(collect($rows)
            ->filter(static fn(array $row): bool => (float) $row['avgActualMinutes'] > 0.0 || (float) $row['avgPlannedMinutes'] > 0.0)
            ->sortByDesc('avgActualMinutes')
            ->take(20)
            ->map(static fn(array $row): array => [
                'x' => (string) $row['entryTypeName'],
                'y' => (float) $row['avgActualMinutes'],
                'y2' => (float) $row['avgPlannedMinutes'],
            ])
            ->all());

        return $this->pdfDownload('reports.pdf.entry-types', [
            'rows' => $rows,
            'label' => $label,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Plan vs. Ist je Auftragstyp'),
                'unit' => __('Min.'),
                'xLabel' => __('Auftragstyp'),
                'yLabel' => __('Ø Ist (Min.)'),
                'y2Label' => __('Ø Plan (Min.)'),
                'series' => $chartSeries,
            ],
        ], $filename, 'landscape', $request, 'entry-types-analysis', $filters);
    }

    /**
     * @return array<int, string>
     */
    public static function statusOptions(): array {
        $options = [];
        foreach (DiaryStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
