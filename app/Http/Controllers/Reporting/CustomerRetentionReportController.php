<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerRetentionReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\User;
use App\Services\Reporting\CustomerRetentionReportBuilder;
use App\Support\CarbonFmt;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Kundenbindung & Kohorten (MVP-466, Feature 002): Wie gut hält das
 * Unternehmen seine Kunden — Kohorten-Retention nach Erstleistungsjahr
 * und Kundenbestandsbrücke über den Berichtszeitraum.
 */
class CustomerRetentionReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function __construct(private readonly CustomerRetentionReportBuilder $builder) {}

    public function index(Request $request): View|Response|SymfonyResponse {
        $authUser = Auth::user();
        $allowed = $authUser instanceof User
            && ($authUser->isAdmin() || $authUser->can(Permission::ReportView->value));
        abort_unless($allowed, 403);

        [$from, $to] = $this->resolveRange($request);
        $label = CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to);

        $lostDays = max(30, (int) $request->integer('lost_days', 365));
        $filterFields = ['include_excluded'];
        $filters = $this->standardFilters($request, $filterFields, $from, $to);

        $result = $this->builder->build($from, $to, 6, $lostDays, $filters->excludedCustomerIds);

        $exportFilters = array_merge(['lost_days' => $lostDays], $filters->toAuditArray());

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($result, $from->toDateString(), $to->toDateString(), $exportFilters, $request);
        }

        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($result, $label, $from->toDateString(), $to->toDateString(), $exportFilters, $request);
        }

        $drilldownParams = array_merge(
            $filters->toQueryParams(),
            $lostDays !== 365 ? ['lost_days' => $lostDays] : [],
        );

        return view('reports.customer-retention', [
            'cohorts' => $result['cohorts'],
            'bridge' => $result['bridge'],
            'kpis' => $result['kpis'],
            'lostDays' => $lostDays,
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'standardFilters' => $filters,
            'filterFields' => $filterFields,
            'cohortHeatmap' => $this->cohortHeatmap($result['cohorts'], $drilldownParams),
            'bridgeSeries' => $this->bridgeSeries($result['bridge'], withAnchors: true),
            ...$this->standardFilterOptions($filterFields, $filters),
        ]);
    }

    /**
     * Drilldown (MVP-470): Kunden einer Kohorte (Erstleistungsjahr),
     * optional mit Aktiv-Kennzeichen für ein Zieljahr — beantwortet „wer
     * steckt hinter dieser Heatmap-Zelle?".
     */
    public function drilldown(Request $request): View|Response {
        $authUser = Auth::user();
        $allowed = $authUser instanceof User
            && ($authUser->isAdmin() || $authUser->can(Permission::ReportView->value));
        abort_unless($allowed, 403);

        [$from, $to] = $this->resolveRange($request);
        $label = CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to);

        $cohort = (int) $request->integer('cohort');
        abort_unless($cohort >= 1990 && $cohort <= 2100, 404);
        $year = $request->filled('year') ? (int) $request->integer('year') : null;

        $lostDays = max(30, (int) $request->integer('lost_days', 365));
        $filters = $this->standardFilters($request, ['include_excluded'], $from, $to);

        $rows = $this->builder->cohortCustomers($from, $to, $cohort, $year, $lostDays, $filters->excludedCustomerIds);

        $exportFilters = array_merge(['cohort' => $cohort, 'year' => $year, 'lost_days' => $lostDays], $filters->toAuditArray());

        if ($request->query('export') === 'csv') {
            $out = [['Kunde', 'ErsteLeistung', 'LetzteLeistung', $year !== null ? 'AktivIn' . $year : 'AktivImZieljahr']];
            foreach ($rows as $row) {
                $out[] = [$row['customerName'], $row['firstActivity'], $row['lastActivity'], $row['activeInYear'] === null ? '' : ($row['activeInYear'] ? 'ja' : 'nein')];
            }

            return $this->csvWithMetadata($out, sprintf('kohorte_%d_%s_%s.csv', $cohort, $from->toDateString(), $to->toDateString()), 'customer-retention-cohort', $exportFilters, $request);
        }

        return view('reports.drilldown.customer-retention-cohort', [
            'rows' => $rows,
            'cohort' => $cohort,
            'year' => $year,
            'lostDays' => $lostDays,
            'label' => $label,
            'standardFilters' => $filters,
        ]);
    }

    /**
     * Kohorten-Matrix im Heatmap-Kontrakt (Zeilen: Erstleistungsjahr,
     * Spalten: Jahr +Offset, Wert: Anteil aktiver Kunden in %).
     * Zeilenlabel und Zellen verlinken in den Kohorten-Drilldown (MVP-470).
     *
     * @param  array{years: list<int>, rows: list<array{year:int, size:int, cells: list<?float>}>}  $cohorts
     * @param  array<string, mixed>  $drilldownParams
     * @return array{rows: list<array{label: string, url: string, cells: list<?array{value: float, title: string, url: string}>}>, colLabels: list<string>, max: ?float}
     */
    private function cohortHeatmap(array $cohorts, array $drilldownParams = []): array {
        $offsets = range(0, count($cohorts['years']) - 1);

        // Kohorten ohne Kunden wären reine Null-Zeilen; ohne jede Kohorte
        // greift der Leerzustand der Komponente (§Diagramm-UX).
        $rows = array_values(array_filter($cohorts['rows'], static fn(array $row): bool => $row['size'] > 0));

        return [
            'rows' => array_map(static fn(array $row): array => [
                'label' => $row['year'] . ' (n=' . $row['size'] . ')',
                'url' => route('reports.customer-retention.drilldown', array_merge($drilldownParams, ['cohort' => $row['year']])),
                'cells' => array_map(static fn(?float $pct, int $offset): ?array => $pct === null ? null : [
                    'value' => $pct,
                    'title' => NumberHelper::toGermanFormat($pct, 1) . ' %',
                    'url' => route('reports.customer-retention.drilldown', array_merge($drilldownParams, [
                        'cohort' => $row['year'],
                        'year' => $row['year'] + $offset,
                    ])),
                ], $row['cells'], array_keys($row['cells'])),
            ], $rows),
            'colLabels' => array_map(static fn(int $offset): string => '+' . $offset, $offsets),
            'max' => $rows === [] ? null : 100.0,
        ];
    }

    /**
     * Bestandsbrücke im Waterfall-Kontrakt; Drilldown-Listen stehen auf der
     * Seite selbst (gleiche Daten, §Diagramm-UX-Tabellenparität) — die
     * Schritte verlinken per In-Page-Anker auf die Namenslisten (MVP-470).
     *
     * @param  array{start:int, end:int, new: list<array{customerId:int, customerName:string}>, reactivated: list<array{customerId:int, customerName:string}>, newChurned: list<array{customerId:int, customerName:string}>, lost: list<array{customerId:int, customerName:string}>}  $bridge
     * @return list<array{x: string, y: int, url: ?string}>
     */
    private function bridgeSeries(array $bridge, bool $withAnchors = false): array {
        $anchor = static fn(string $id): ?string => $withAnchors ? '#' . $id : null;

        // „Neukunden" enthält ALLE Erstkunden des Zeitraums (auch die wieder
        // inaktiven) — nur so geht die Brücke exakt auf:
        // Start + Neu + Zurückgewonnen − Neu-wieder-inaktiv − Verloren = Ende.
        return array_values(array_filter([
            ['x' => (string) __('Neukunden'), 'y' => count($bridge['new']) + count($bridge['newChurned']), 'url' => $anchor('neukunden')],
            ['x' => (string) __('Zurückgewonnen'), 'y' => count($bridge['reactivated']), 'url' => $anchor('zurueckgewonnen')],
            ['x' => (string) __('Neu, wieder inaktiv'), 'y' => -count($bridge['newChurned']), 'url' => $anchor('neukunden')],
            ['x' => (string) __('Verloren'), 'y' => -count($bridge['lost']), 'url' => $anchor('verloren')],
        ], static fn(array $step): bool => $step['y'] !== 0));
    }

    /**
     * @param  array{cohorts: array{years: list<int>, rows: list<array{year:int, size:int, cells: list<?float>}>}, bridge: array{start:int, end:int, new: list<array{customerId:int, customerName:string}>, reactivated: list<array{customerId:int, customerName:string}>, newChurned: list<array{customerId:int, customerName:string}>, lost: list<array{customerId:int, customerName:string}>}, kpis: array{returningRate:?float, avgCustomerAgeYears:?float, newCount:int, lostCount:int, endActive:int}}  $result
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $result, string $from, string $to, array $filters, Request $request): Response {
        $filename = sprintf('kundenbindung_%s_%s.csv', $from, $to);
        $out = [];
        $out[] = array_merge(['Kohorte', 'Kunden'], array_map(static fn(int $i): string => 'Jahr+' . $i, range(0, count($result['cohorts']['years']) - 1)));
        foreach ($result['cohorts']['rows'] as $row) {
            $out[] = array_merge(
                [$row['year'], $row['size']],
                array_map(static fn(?float $pct): string => $pct === null ? '' : NumberHelper::toUSFormat($pct, 1), $row['cells']),
            );
        }

        $out[] = [];
        $out[] = ['Bestandsbruecke', 'Anzahl'];
        $out[] = ['BestandStart', $result['bridge']['start']];
        $out[] = ['Neukunden', count($result['bridge']['new'])];
        $out[] = ['Zurueckgewonnen', count($result['bridge']['reactivated'])];
        $out[] = ['NeuWiederInaktiv', -count($result['bridge']['newChurned'])];
        $out[] = ['Verloren', -count($result['bridge']['lost'])];
        $out[] = ['BestandEnde', $result['bridge']['end']];

        return $this->csvWithMetadata($out, $filename, 'customer-retention', $filters, $request);
    }

    /**
     * @param  array{cohorts: array{years: list<int>, rows: list<array{year:int, size:int, cells: list<?float>}>}, bridge: array{start:int, end:int, new: list<array{customerId:int, customerName:string}>, reactivated: list<array{customerId:int, customerName:string}>, newChurned: list<array{customerId:int, customerName:string}>, lost: list<array{customerId:int, customerName:string}>}, kpis: array{returningRate:?float, avgCustomerAgeYears:?float, newCount:int, lostCount:int, endActive:int}}  $result
     * @param  array<string, mixed>  $filters
     */
    private function exportPdf(array $result, string $label, string $from, string $to, array $filters, Request $request): SymfonyResponse {
        $filename = sprintf('kundenbindung_%s_%s.pdf', $from, $to);

        return $this->pdfDownload('reports.pdf.customer-retention', [
            'cohorts' => $result['cohorts'],
            'bridge' => $result['bridge'],
            'kpis' => $result['kpis'],
            'label' => $label,
            'chart' => [
                'type' => 'waterfall-h',
                'title' => __('Kundenbestandsbrücke'),
                'unit' => __('Kunden'),
                'xLabel' => __('Schritt'),
                'startValue' => $result['bridge']['start'],
                'startLabel' => __('Bestand Start'),
                'endLabel' => __('Bestand Ende'),
                'series' => $this->bridgeSeries($result['bridge']),
            ],
        ], $filename, 'landscape', $request, 'customer-retention', $filters);
    }
}
