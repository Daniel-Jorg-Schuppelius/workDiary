<?php
/*
 * Created on   : Fri Aug 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierAnalysisReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\{Supplier, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reporting\SupplierAnalysisReportBuilder;
use App\Support\{CarbonFmt, Sqid};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Lieferantenanalyse (Feature 002, MVP-472): Ausgaben, Beschaffungsvolumen,
 * offene Verbindlichkeiten, Ausgabenkonzentration (Klumpenrisiko) und
 * Ausgabentrend je Lieferant — das Einkaufs-Pendant zur Kundenanalyse.
 *
 * Recht: Finanzsicht → nur report.view/Admin (Ausgaben sind Finanzdaten,
 * gleiche Schranke wie Wirtschaftlichkeit/Kundenwert). Ausgaben stammen aus
 * dem Lexoffice-Beleg-Spiegel und funktionieren OHNE Lager-Modul; Bestell-
 * Kennzahlen kommen nur mit `module.lager` hinzu.
 */
class SupplierAnalysisReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function __construct(private readonly SupplierAnalysisReportBuilder $builder) {}

    public function index(Request $request): View|Response|SymfonyResponse {
        $authUser = Auth::user();
        $allowed = $authUser instanceof User
            && ($authUser->isAdmin() || $authUser->can(Permission::ReportView->value));
        abort_unless($allowed, 403);

        [$from, $to] = $this->resolveRange($request);
        $label = CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to);

        $minSpend = max(0, (int) $request->integer('min_spend', 0));
        $hideZero = $request->boolean('hide_zero');
        $withProcurement = app(FeatureFlagResolver::class)->isEnabled('module.lager');

        $result = $this->builder->build($from, $to, $withProcurement);
        $rows = collect($result['rows'])
            ->filter(static fn(array $row): bool => $row['spend'] >= $minSpend)
            ->when($hideZero, fn($c) => $c->filter(static fn(array $row): bool => $row['spend'] > 0
                || $row['voucherCount'] > 0
                || ($row['orderCount'] ?? 0) > 0
                || ($row['openOrderCount'] ?? 0) > 0))
            ->values();

        $exportFilters = ['min_spend' => $minSpend, 'hide_zero' => $hideZero, 'with_procurement' => $withProcurement];

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv(array_values($rows->all()), $withProcurement, $from->toDateString(), $to->toDateString(), $exportFilters, $request);
        }

        if ($request->query('export') === 'pdf') {
            return $this->exportPdf(array_values($rows->all()), $result['concentration'], $label, $from->toDateString(), $to->toDateString(), $this->spendSeries(array_values($rows->all()), $from, $to), $withProcurement, $exportFilters, $request);
        }

        return view('reports.suppliers', [
            'rows' => $rows,
            'concentration' => $result['concentration'],
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'minSpend' => $minSpend,
            'hideZero' => $hideZero,
            'withProcurement' => $withProcurement,
            'spendSeries' => $this->spendSeries(array_values($rows->all()), $from, $to),
            'monthlySpendSeries' => $this->builder->monthlySpendSeries($from, $to, $this->globalUnit()),
            'openSeries' => $this->openSeries(array_values($rows->all()), $from, $to),
            'periodPhrase' => $this->periodPhrase($this->bucketGranularity($from, $to)),
            'periodAxis' => $this->periodAxisLabel($this->bucketGranularity($from, $to)),
        ]);
    }

    /**
     * Drilldown-Ziel eines Lieferanten: Belege-Abschnitt der Detailseite,
     * eingegrenzt auf den Report-Zeitraum.
     */
    private function supplierVoucherUrl(int $supplierId, \Carbon\CarbonImmutable $from, \Carbon\CarbonImmutable $to): string {
        return route('suppliers.show', [
            'supplier' => Sqid::encode(Supplier::class, $supplierId),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]) . '#vouchers';
    }

    /**
     * Ausgaben je Lieferant (Top 20) — Pareto am Screen, bar-h im PDF;
     * Drilldown öffnet die Belege der Lieferanten-Detailseite im Zeitraum.
     *
     * @param  list<array{supplierId:int, supplierName:string, spend:float, voucherCount:int, avgVoucher:float, openAmount:float, recencyDays:?int, lastVoucher:?string, spendPrev:float, trendPct:?float, orderCount:?int, openOrderCount:?int}>  $rows
     * @return list<array{x: string, y: float, url: string}>
     */
    private function spendSeries(array $rows, \Carbon\CarbonImmutable $from, \Carbon\CarbonImmutable $to): array {
        return array_values(collect($rows)
            ->filter(static fn(array $row): bool => $row['spend'] > 0)
            ->sortByDesc('spend')
            ->take(20)
            ->map(fn(array $row): array => [
                'x' => $row['supplierName'],
                'y' => round($row['spend'], 2),
                'url' => $this->supplierVoucherUrl($row['supplierId'], $from, $to),
            ])
            ->all());
    }

    /**
     * Offener Betrag je Lieferant (Top 15) — offene Verbindlichkeiten aus dem
     * Beleg-Spiegel; Drilldown öffnet die Belege der Detailseite im Zeitraum.
     *
     * @param  list<array{supplierId:int, supplierName:string, spend:float, voucherCount:int, avgVoucher:float, openAmount:float, recencyDays:?int, lastVoucher:?string, spendPrev:float, trendPct:?float, orderCount:?int, openOrderCount:?int}>  $rows
     * @return list<array{x: string, y: float, url: string}>
     */
    private function openSeries(array $rows, \Carbon\CarbonImmutable $from, \Carbon\CarbonImmutable $to): array {
        return array_values(collect($rows)
            ->filter(static fn(array $row): bool => $row['openAmount'] > 0)
            ->sortByDesc('openAmount')
            ->take(15)
            ->map(fn(array $row): array => [
                'x' => $row['supplierName'],
                'y' => round($row['openAmount'], 2),
                'url' => $this->supplierVoucherUrl($row['supplierId'], $from, $to),
            ])
            ->all());
    }

    /**
     * @param  list<array{supplierId:int, supplierName:string, spend:float, voucherCount:int, avgVoucher:float, openAmount:float, recencyDays:?int, lastVoucher:?string, spendPrev:float, trendPct:?float, orderCount:?int, openOrderCount:?int}>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $rows, bool $withProcurement, string $from, string $to, array $filters, Request $request): Response {
        $filename = sprintf('lieferantenanalyse_%s_%s.csv', $from, $to);
        $out = [];
        $header = [
            'Lieferant',
            'AusgabenEUR',
            'Belege',
            'DurchschnittBelegEUR',
            'OffenerBetragEUR',
            'TageSeitLetztemBeleg',
            'TrendProzent',
        ];
        if ($withProcurement) {
            $header[] = 'Bestellungen';
            $header[] = 'OffeneBestellungen';
        }
        $out[] = $header;

        foreach ($rows as $row) {
            $line = [
                $row['supplierName'],
                NumberHelper::toUSFormat($row['spend'], 2),
                $row['voucherCount'],
                NumberHelper::toUSFormat($row['avgVoucher'], 2),
                NumberHelper::toUSFormat($row['openAmount'], 2),
                $row['recencyDays'] ?? '',
                $row['trendPct'] ?? '',
            ];
            if ($withProcurement) {
                $line[] = $row['orderCount'] ?? 0;
                $line[] = $row['openOrderCount'] ?? 0;
            }
            $out[] = $line;
        }

        return $this->csvWithMetadata($out, $filename, 'supplier-analysis', $filters, $request);
    }

    /**
     * @param  list<array{supplierId:int, supplierName:string, spend:float, voucherCount:int, avgVoucher:float, openAmount:float, recencyDays:?int, lastVoucher:?string, spendPrev:float, trendPct:?float, orderCount:?int, openOrderCount:?int}>  $rows
     * @param  array{totalSpend:float, top5Share:?float, top10Share:?float, hhi:?int, activeSuppliers:int}  $concentration
     * @param  list<array{x: string, y: float, url: string}>  $spendSeries
     * @param  array<string, mixed>  $filters
     */
    private function exportPdf(array $rows, array $concentration, string $label, string $from, string $to, array $spendSeries, bool $withProcurement, array $filters, Request $request): SymfonyResponse {
        $filename = sprintf('lieferantenanalyse_%s_%s.pdf', $from, $to);

        return $this->pdfDownload('reports.pdf.suppliers', [
            'rows' => $rows,
            'concentration' => $concentration,
            'withProcurement' => $withProcurement,
            'label' => $label,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Ausgaben je Lieferant (Top 20)'),
                'unit' => '€',
                'xLabel' => __('Lieferant'),
                'yLabel' => '€',
                'series' => $spendSeries,
            ],
        ], $filename, 'landscape', $request, 'supplier-analysis', $filters);
    }
}
