<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetAnalysisReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{Asset, Customer};
use App\Services\Reporting\{AssetAnalysisReportBuilder, ReportFilters};
use App\Support\{CarbonFmt, Sqid};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Produkt-/Objektanalyse (MVP-041).
 *
 * Aggregiert Aufträge, offene Punkte und Defekte je Asset / Produktgruppe / Modell.
 * Vereinfachtes MVP gemäss ../WorkDiary-Architecture/produkt-analyse.md auf Basis der vorhandenen
 * Strukturen (Asset, DiaryEntry.asset_id, OpenIssue subject, Protocol Defect).
 */
class AssetAnalysisReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function __construct(private readonly AssetAnalysisReportBuilder $builder) {
    }

    public function index(Request $request): View|Response|SymfonyResponse {
        [$from, $to] = $this->resolveRange($request);
        $label = CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to);

        // Standard-Set bewusst nur Kunde — Assets haben keine natürliche
        // Status-/Projektdimension; category_code/manufacturer/group_by
        // bleiben Spezialfilter des Reports.
        $filterFields = ['customer', 'include_excluded'];
        $filters = $this->standardFilters($request, $filterFields, $from, $to);
        // Legacy-Parameter customer_id (alte Bookmarks) ins Standard-Set
        // übernehmen, damit Partial, Links und Audit denselben Stand sehen.
        $customerId = $filters->customerId ?? Sqid::decodeOrNumeric(Customer::class, $request->query('customer_id'));
        if ($customerId !== $filters->customerId) {
            $filters = new ReportFilters(
                from: $from,
                to: $to,
                customerId: $customerId,
                excludedCustomerIds: $filters->excludedCustomerIds,
                includeExcludedCustomers: $filters->includeExcludedCustomers,
            );
        }

        // Feature 002: Ausblendung greift nur ohne explizite Kundenwahl
        // (gleiche Übersteuerungsregel wie ReportFilters::customerExclusionActive()).
        $excludedCustomerIds = $customerId === null ? $filters->excludedCustomerIds : [];

        $categoryCode = $request->filled('category_code') ? (string) $request->string('category_code') : null;
        $manufacturer = $request->filled('manufacturer') ? (string) $request->string('manufacturer') : null;
        $groupBy = (string) $request->string('group_by', 'asset');
        if (! in_array($groupBy, ['asset', 'group', 'model'], true)) {
            $groupBy = 'asset';
        }

        $rows = $this->builder->build($from, $to, $customerId, $categoryCode, $manufacturer, $groupBy, $excludedCustomerIds);

        $exportContext = array_merge([
            'category_code' => $categoryCode,
            'manufacturer' => $manufacturer,
            'group_by' => $groupBy,
        ], $filters->toAuditArray());

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($rows, $groupBy, $from->toDateString(), $to->toDateString(), $exportContext, $request);
        }

        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($rows, $groupBy, $label, $from->toDateString(), $to->toDateString(), $this->defectsSeries($rows), $exportContext, $request);
        }

        return view('reports.assets', [
            'rows' => $rows,
            'label' => $label,
            'from' => $from,
            'to' => $to,
            'standardFilters' => $filters,
            'filterFields' => $filterFields,
            'defectsSeries' => $this->defectsSeries($rows),
            'defectRateSeries' => $this->defectRateSeries($rows),
            'maintenanceSeries' => $this->maintenanceSeries($rows),
            ...$this->standardFilterOptions($filterFields, $filters),
            'categories' => Asset::query()
                ->whereNotNull('category_code')
                ->orderBy('category_code')
                ->distinct()
                ->pluck('category_code')
                ->filter()
                ->values(),
            'manufacturers' => Asset::query()
                ->whereNotNull('manufacturer')
                ->orderBy('manufacturer')
                ->distinct()
                ->pluck('manufacturer')
                ->filter()
                ->values(),
            'customerId' => $customerId,
            'categoryCode' => $categoryCode,
            'manufacturer' => $manufacturer,
            'groupBy' => $groupBy,
        ]);
    }

    /**
     * Defekt-Pareto (Top 20) der aktiven Gruppierungsebene — Drilldown in die
     * Defektprotokolle (der AssetDrilldownReportController liest die
     * Legacy-Parameternamen aus dem drilldown-Array der Zeile).
     *
     * @param  list<array{key:string,label:string,assetCount:int,entryCount:int,openIssueCount:int,escalationCount:int,defectCount:int,defectRate:float,maintenanceSessions:int,maintenanceMinutes:int,lastIncidentAt:?string,drilldown:array<string,mixed>}>  $rows
     * @return list<array{x: string, y: int, url: string}>
     */
    private function defectsSeries(array $rows): array {
        return array_values(collect($rows)
            ->filter(static fn(array $row): bool => $row['defectCount'] > 0)
            ->sortByDesc('defectCount')
            ->take(20)
            ->map(static fn(array $row): array => [
                'x' => $row['label'],
                'y' => $row['defectCount'],
                'url' => route('reports.assets.drilldown.protocols', $row['drilldown']),
            ])
            ->all());
    }

    /**
     * Defektrate (%) je Gruppierungsebene, Top 15 — eine Zeitreihe geben die
     * Builder-Daten nicht her (nur Aggregatzeilen je Gruppe).
     *
     * @param  list<array{key:string,label:string,assetCount:int,entryCount:int,openIssueCount:int,escalationCount:int,defectCount:int,defectRate:float,maintenanceSessions:int,maintenanceMinutes:int,lastIncidentAt:?string,drilldown:array<string,mixed>}>  $rows
     * @return list<array{x: string, y: float, url: string}>
     */
    private function defectRateSeries(array $rows): array {
        return array_values(collect($rows)
            ->filter(static fn(array $row): bool => $row['defectRate'] > 0.0)
            ->sortByDesc('defectRate')
            ->take(15)
            ->map(static fn(array $row): array => [
                'x' => $row['label'],
                'y' => $row['defectRate'],
                'url' => route('reports.assets.drilldown.protocols', $row['drilldown']),
            ])
            ->all());
    }

    /**
     * Wartungszeit (Stunden) je Gruppierungsebene, Top 15 — aus den
     * Fernwartungs-Sitzungen (RemoteSupport-Plugin). Zeigt, welche Geräte/
     * Modelle die meiste Betreuung ziehen; ohne Plugin leer. Kein Drilldown
     * (Sitzungsliste ist kein eigener Report).
     *
     * @param  list<array{key:string,label:string,assetCount:int,entryCount:int,openIssueCount:int,escalationCount:int,defectCount:int,defectRate:float,maintenanceSessions:int,maintenanceMinutes:int,lastIncidentAt:?string,drilldown:array<string,mixed>}>  $rows
     * @return list<array{x: string, y: float}>
     */
    private function maintenanceSeries(array $rows): array {
        return array_values(collect($rows)
            ->filter(static fn(array $row): bool => $row['maintenanceMinutes'] > 0)
            ->sortByDesc('maintenanceMinutes')
            ->take(15)
            ->map(static fn(array $row): array => [
                'x' => $row['label'],
                'y' => round($row['maintenanceMinutes'] / 60, 1),
            ])
            ->all());
    }

    /**
     * @param  list<array{
     *   key:string,label:string,assetCount:int,entryCount:int,openIssueCount:int,
     *   escalationCount:int,defectCount:int,defectRate:float,maintenanceSessions:int,
     *   maintenanceMinutes:int,lastIncidentAt:?string,
     *   drilldown:array<string,mixed>
     * }>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $rows, string $groupBy, string $from, string $to, array $filters, Request $request): Response {
        $filename = sprintf('produktanalyse_%s_%s_%s.csv', $groupBy, $from, $to);

        $out = [];
        $out[] = [
            match ($groupBy) {
                'group' => 'Produktgruppe',
                'model' => 'Modell',
                default => 'Asset'
            },
            'Assets',
            'Auftraege',
            'OffenePunkte',
            'Eskaliert',
            'Defekte',
            'DefektrateProzent',
            'Wartungssitzungen',
            'WartungszeitMinuten',
            'LetzterVorfall',
        ];

        foreach ($rows as $row) {
            $out[] = [
                $row['label'],
                $row['assetCount'],
                $row['entryCount'],
                $row['openIssueCount'],
                $row['escalationCount'],
                $row['defectCount'],
                NumberHelper::toUSFormat((float) $row['defectRate'], 2),
                $row['maintenanceSessions'],
                $row['maintenanceMinutes'],
                $row['lastIncidentAt'] ?? '',
            ];
        }

        return $this->csvWithMetadata(
            $out,
            $filename,
            'assets-analysis',
            $filters,
            $request,
        );
    }

    /**
     * @param  list<array{
     *   key:string,label:string,assetCount:int,entryCount:int,openIssueCount:int,
     *   escalationCount:int,defectCount:int,defectRate:float,maintenanceSessions:int,
     *   maintenanceMinutes:int,lastIncidentAt:?string,
     *   drilldown:array<string,mixed>
     * }>  $rows
     * @param  list<array{x: string, y: int, url: string}>  $defectsSeries
     * @param  array<string, mixed>  $filters
     */
    private function exportPdf(array $rows, string $groupBy, string $label, string $from, string $to, array $defectsSeries, array $filters, Request $request): SymfonyResponse {
        $filename = sprintf('produktanalyse_%s_%s_%s.pdf', $groupBy, $from, $to);

        return $this->pdfDownload('reports.pdf.assets', [
            'rows' => $rows,
            'label' => $label,
            'groupBy' => $groupBy,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Defekte im Zeitraum (Top 20)'),
                'unit' => __('Defekte'),
                'xLabel' => match ($groupBy) {
                    'group' => __('Produktgruppe'),
                    'model' => __('Modell'),
                    default => __('Asset'),
                },
                'yLabel' => __('Defekte'),
                'series' => $defectsSeries,
            ],
        ], $filename, 'landscape', $request, 'assets-analysis', $filters);
    }
}
