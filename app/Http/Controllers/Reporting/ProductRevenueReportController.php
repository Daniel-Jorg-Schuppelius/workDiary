<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductRevenueReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, WritesReportCsv};
use App\Models\Article;
use App\Services\Reporting\ProductRevenueReportBuilder;
use App\Support\CarbonFmt;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Umsatz je Produkt (MVP-705, Feature 140): Menge/Nettoumsatz/Anteil je
 * Artikel aus lokalen Rechnungen. Recht wie der Abrechnungsbericht
 * (Admin oder timeEntry.viewAny — Buchhaltung, MVP-460).
 */
class ProductRevenueReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use WritesReportCsv;

    private const TOP_N_DEFAULT = 10;

    public function __construct(private readonly ProductRevenueReportBuilder $builder) {}

    public function index(Request $request): View|Response|SymfonyResponse {
        abort_unless($this->viewerSeesAllTimes(), 403);

        [$from, $to] = $this->resolveRange($request);
        $label = CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to);
        $topN = max(3, min(50, (int) $request->integer('top_n', self::TOP_N_DEFAULT)));

        $result = $this->builder->build($from, $to);
        $series = $this->topSeries($result['rows'], $topN);
        $filters = ['top_n' => $topN];

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($result['rows'], $from->toDateString(), $to->toDateString(), $filters, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($result, $label, $from->toDateString(), $to->toDateString(), $series, $filters, $request);
        }

        return view('reports.product-revenue', [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'withoutArticle' => $result['withoutArticle'],
            'articleCount' => $result['articleCount'],
            'series' => $series,
            'topN' => $topN,
            'from' => $from,
            'to' => $to,
            'label' => $label,
        ]);
    }

    /**
     * Top-N-Artikel nach Nettoumsatz; der Sammelposten ohne Artikel bleibt
     * dem Chart fern (er würde jede Rangfolge dominieren).
     *
     * @param  list<array{articleId: ?int, number: ?string, name: string, unit: ?string, quantity: float, net: float, share: ?float, invoices: int}>  $rows
     * @return list<array{x: string, y: float, url: string}>
     */
    private function topSeries(array $rows, int $topN): array {
        return array_values(collect($rows)
            ->filter(static fn(array $row): bool => $row['articleId'] !== null && $row['net'] > 0)
            ->take($topN)
            ->map(static fn(array $row): array => [
                'x' => $row['name'],
                'y' => round($row['net'], 2),
                'url' => route('articles.show', \App\Support\Sqid::encode(Article::class, $row['articleId'])),
            ])
            ->all());
    }

    /**
     * @param  list<array{articleId: ?int, number: ?string, name: string, unit: ?string, quantity: float, net: float, share: ?float, invoices: int}>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $rows, string $from, string $to, array $filters, Request $request): Response {
        $filename = sprintf('umsatz_je_produkt_%s_%s.csv', $from, $to);
        $out = [['Artikelnummer', 'Artikel', 'Einheit', 'Menge', 'NettoumsatzEUR', 'AnteilProzent', 'Rechnungen']];
        foreach ($rows as $row) {
            $out[] = [
                $row['number'] ?? '',
                $row['name'],
                $row['unit'] ?? '',
                NumberHelper::toUSFormat($row['quantity'], 3),
                NumberHelper::toUSFormat($row['net'], 2),
                $row['share'] !== null ? NumberHelper::toUSFormat($row['share'], 1) : '',
                $row['invoices'],
            ];
        }

        return $this->csvWithMetadata($out, $filename, 'product-revenue', $filters, $request);
    }

    /**
     * @param  array{rows: list<array{articleId: ?int, number: ?string, name: string, unit: ?string, quantity: float, net: float, share: ?float, invoices: int}>, total: float, withoutArticle: float, articleCount: int}  $result
     * @param  list<array{x: string, y: float, url: string}>  $series
     * @param  array<string, mixed>  $filters
     */
    private function exportPdf(array $result, string $label, string $from, string $to, array $series, array $filters, Request $request): SymfonyResponse {
        $filename = sprintf('umsatz_je_produkt_%s_%s.pdf', $from, $to);

        return $this->pdfDownload('reports.pdf.product-revenue', [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'withoutArticle' => $result['withoutArticle'],
            'articleCount' => $result['articleCount'],
            'label' => $label,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Nettoumsatz je Artikel (Top :n)', ['n' => count($series)]),
                'unit' => '€',
                'xLabel' => __('Artikel'),
                'yLabel' => '€',
                'series' => $series,
            ],
        ], $filename, 'portrait', $request, 'product-revenue', $filters);
    }
}
