<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentBehaviorReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\User;
use App\Services\Reporting\{PaymentBehaviorReportBuilder, ReportFilters};
use App\Support\CarbonFmt;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Zahlungsverhalten & Forderungstrend (MVP-468, Feature 002): Wie schnell
 * zahlen Kunden, wohin entwickelt sich die Liquiditätsbindung? Quellen:
 * lokale Rechnungen plus Lexoffice-Beleg-Spiegel (Phase-54-Nachtrag) —
 * funktioniert damit auch bei externer Rechnungshoheit.
 */
class PaymentBehaviorReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function __construct(private readonly PaymentBehaviorReportBuilder $builder) {}

    public function index(Request $request): View|Response|SymfonyResponse {
        $authUser = Auth::user();
        $allowed = $authUser instanceof User
            && ($authUser->isAdmin() || $authUser->can(Permission::ReportView->value));
        abort_unless($allowed, 403);

        [$from, $to] = $this->resolveRange($request);
        $label = CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to);

        $filterFields = ['customer', 'include_excluded'];
        $filters = $this->standardFilters($request, $filterFields, $from, $to);

        // Feature 002: Ausblendung greift nur ohne explizite Kundenwahl.
        $excludedCustomerIds = $filters->customerId === null ? $filters->excludedCustomerIds : [];

        $result = $this->builder->build($from, $to, $filters->customerId, $excludedCustomerIds);

        $exportFilters = $filters->toAuditArray();

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($result, $from->toDateString(), $to->toDateString(), $exportFilters, $request);
        }

        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($result, $label, $from->toDateString(), $to->toDateString(), $exportFilters, $request);
        }

        return view('reports.payment-behavior', [
            'kpis' => $result['kpis'],
            'payBox' => $this->withCustomerUrls($result['payBox'], $filters),
            'delayTop' => $result['delayTop'],
            'overdue' => $result['overdue'],
            'hasData' => $result['hasData'],
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'standardFilters' => $filters,
            'filterFields' => $filterFields,
            'dsoSeries' => $this->monthlySeries($result['monthly'], 'dso'),
            'payDaysSeries' => $this->monthlySeries($result['monthly'], 'avgPayDays'),
            'delaySeries' => $this->delaySeries($result['delayTop'], $filters),
            ...$this->standardFilterOptions($filterFields, $filters),
        ]);
    }

    /**
     * @param  list<array{month:string, dso:?float, avgPayDays:?float}>  $monthly
     * @param  'dso'|'avgPayDays'  $key
     * @return list<array{x: string, y: float}>
     */
    private function monthlySeries(array $monthly, string $key): array {
        $series = array_values(array_filter(array_map(static fn(array $m): ?array => $m[$key] === null ? null : [
            'x' => $m['month'],
            'y' => $m[$key],
        ], $monthly)));

        return count($series) > 1 ? $series : []; // Ein-Punkt-Linie sagt nichts — Leerzustand.
    }

    /**
     * Selbstfilter-Drilldown (MVP-470): Klick auf einen Kunden zeigt diesen
     * Bericht nur für ihn (Trend/Verteilung/Rechnungen des Kunden).
     */
    private function customerUrl(int $customerId, ReportFilters $filters): string {
        return route('reports.payment-behavior', array_merge($filters->toQueryParams(), [
            'customer' => \App\Support\Sqid::encode(\App\Models\Customer::class, $customerId),
        ]));
    }

    /**
     * @param  list<array{x:string, min:float, q1:float, median:float, q3:float, max:float, n:int, customerId:?int}>  $payBox
     * @return list<array{x:string, min:float, q1:float, median:float, q3:float, max:float, n:int, customerId:?int, url:?string}>
     */
    private function withCustomerUrls(array $payBox, ReportFilters $filters): array {
        return array_map(fn(array $box): array => [
            ...$box,
            'url' => $box['customerId'] !== null ? $this->customerUrl($box['customerId'], $filters) : null,
        ], $payBox);
    }

    /**
     * @param  list<array{customerId:int, customerName:string, avgDelay:float, invoices:int}>  $delayTop
     * @return list<array{x: string, y: float, url: string}>
     */
    private function delaySeries(array $delayTop, ReportFilters $filters): array {
        return array_map(fn(array $row): array => [
            'x' => $row['customerName'],
            'y' => $row['avgDelay'],
            'url' => $this->customerUrl($row['customerId'], $filters),
        ], array_values(array_filter($delayTop, static fn(array $row): bool => $row['avgDelay'] > 0)));
    }

    /**
     * @param  array{kpis: array{dso:?float, avgPayDays:?float, onTimeShare:?float, overdueCount:int, overdueTotal:float, paidCount:int}, monthly: list<array{month:string, dso:?float, avgPayDays:?float}>, payBox: list<array{x:string, min:float, q1:float, median:float, q3:float, max:float, n:int}>, delayTop: list<array{customerId:int, customerName:string, avgDelay:float, invoices:int}>, overdue: list<array{invoiceId:?int, number:string, customerId:int, customerName:string, dueOn:string, daysOverdue:int, total:float}>, hasData: bool}  $result
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $result, string $from, string $to, array $filters, Request $request): Response {
        $filename = sprintf('zahlungsverhalten_%s_%s.csv', $from, $to);
        $out = [];
        $out[] = ['Kennzahl', 'Wert'];
        $out[] = ['DSO_Tage', $result['kpis']['dso'] !== null ? NumberHelper::toUSFormat($result['kpis']['dso'], 1) : ''];
        $out[] = ['DurchschnittZahldauerTage', $result['kpis']['avgPayDays'] !== null ? NumberHelper::toUSFormat($result['kpis']['avgPayDays'], 1) : ''];
        $out[] = ['PuenktlichProzent', $result['kpis']['onTimeShare'] !== null ? NumberHelper::toUSFormat($result['kpis']['onTimeShare'], 1) : ''];
        $out[] = ['UeberfaelligAnzahl', $result['kpis']['overdueCount']];
        $out[] = ['UeberfaelligSummeEUR', NumberHelper::toUSFormat($result['kpis']['overdueTotal'], 2)];

        $out[] = [];
        $out[] = ['Monat', 'DSO_Tage', 'DurchschnittZahldauerTage'];
        foreach ($result['monthly'] as $m) {
            $out[] = [
                $m['month'],
                $m['dso'] !== null ? NumberHelper::toUSFormat($m['dso'], 1) : '',
                $m['avgPayDays'] !== null ? NumberHelper::toUSFormat($m['avgPayDays'], 1) : '',
            ];
        }

        $out[] = [];
        $out[] = ['Kunde', 'DurchschnittVerzugTage', 'Rechnungen'];
        foreach ($result['delayTop'] as $row) {
            $out[] = [$row['customerName'], NumberHelper::toUSFormat($row['avgDelay'], 1), $row['invoices']];
        }

        $out[] = [];
        $out[] = ['UeberfaelligeRechnung', 'Kunde', 'Faellig', 'TageUeberfaellig', 'BetragEUR'];
        foreach ($result['overdue'] as $row) {
            $out[] = [$row['number'], $row['customerName'], $row['dueOn'], $row['daysOverdue'], NumberHelper::toUSFormat($row['total'], 2)];
        }

        return $this->csvWithMetadata($out, $filename, 'payment-behavior', $filters, $request);
    }

    /**
     * @param  array{kpis: array{dso:?float, avgPayDays:?float, onTimeShare:?float, overdueCount:int, overdueTotal:float, paidCount:int}, monthly: list<array{month:string, dso:?float, avgPayDays:?float}>, payBox: list<array{x:string, min:float, q1:float, median:float, q3:float, max:float, n:int}>, delayTop: list<array{customerId:int, customerName:string, avgDelay:float, invoices:int}>, overdue: list<array{invoiceId:?int, number:string, customerId:int, customerName:string, dueOn:string, daysOverdue:int, total:float}>, hasData: bool}  $result
     * @param  array<string, mixed>  $filters
     */
    private function exportPdf(array $result, string $label, string $from, string $to, array $filters, Request $request): SymfonyResponse {
        $filename = sprintf('zahlungsverhalten_%s_%s.pdf', $from, $to);

        return $this->pdfDownload('reports.pdf.payment-behavior', [
            'kpis' => $result['kpis'],
            'delayTop' => $result['delayTop'],
            'overdue' => $result['overdue'],
            'hasData' => $result['hasData'],
            'label' => $label,
            'chart' => [
                'type' => 'boxplot-table',
                'title' => __('Zahldauer-Verteilung (Tage von Ausstellung bis Zahlung)'),
                'unit' => __('Tage'),
                'xLabel' => __('Kunde'),
                'series' => $result['payBox'],
            ],
        ], $filename, 'landscape', $request, 'payment-behavior', $filters);
    }
}
