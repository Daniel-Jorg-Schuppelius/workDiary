<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetDrilldownReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{BuildsOpenIssueDrilldown, RendersReportPdf, WritesReportCsv};
use App\Models\{Asset, DiaryEntry, OpenIssue, Protocol, User};
use App\Services\Asset\RecurringDefectService;
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Drilldown für die Produkt-/Objektanalyse (MVP-042).
 *
 * Filterbare Asset-Auswahl per: customer_id, category_code, manufacturer,
 * model, asset_id. Liefert die zugrunde liegenden offenen Punkte bzw.
 * Defektprotokolle für eine Kennzahl der Produktanalyse.
 */
class AssetDrilldownReportController extends Controller {
    use BuildsOpenIssueDrilldown;
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    /**
     * Wiederholdefekt-Statistik (Feature 009 → Rang 47): Pareto der Assets nach
     * Defektzahl im Zeitraum, mit Wiederholdefekt-Flag (≥ 3 in 12 Monaten) und
     * Drilldown ins Asset-Dossier.
     */
    public function recurringDefects(Request $request, RecurringDefectService $service): View|Response|SymfonyResponse {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $range = $this->globalDateRange();
        [$from, $to] = $this->globalDateRangeBounds();

        $rows = $service->pareto((int) $user->organization_id, $from, $to);

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            $exportFilters = ['from' => $from->toDateString(), 'to' => $to->toDateString()];

            return $this->exportRecurringDefectsCsv($rows, $exportFilters, $from->toDateString(), $to->toDateString(), $request);
        }

        return view('reports.drilldown.asset-recurring-defects', [
            'rows' => $rows,
            'label' => $range['label'],
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'threshold' => RecurringDefectService::THRESHOLD,
            'windowMonths' => RecurringDefectService::WINDOW_MONTHS,
        ]);
    }

    public function openIssues(Request $request): View|Response|SymfonyResponse {
        $range = $this->globalDateRange();
        [$from, $to] = $this->globalDateRangeBounds();

        $filters = $this->collectFilters($request);
        $escalatedOnly = $request->boolean('escalated');

        $assetIds = $this->assetIds($filters);

        $issuesQuery = $this->openIssueDrilldownQuery($escalatedOnly, function ($query) use ($assetIds): void {
            $query->where('subject_type', Asset::class)
                ->when($assetIds !== [], fn($q) => $q->whereIn('subject_id', $assetIds), fn($q) => $q->whereRaw('1=0'));
        });

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            /** @var list<OpenIssue> $issues */
            $issues = $issuesQuery->clone()->get()->all();
            $exportFilters = $filters + ['escalated' => $escalatedOnly, 'from' => $from->toDateString(), 'to' => $to->toDateString()];

            return $this->exportOpenIssuesCsv($issues, $exportFilters, $from->toDateString(), $to->toDateString(), $escalatedOnly, $request);
        }

        if ($request->query('export') === 'pdf') {
            /** @var list<OpenIssue> $issues */
            $issues = $issuesQuery->clone()->get()->all();
            return $this->exportOpenIssuesPdf(
                $issues,
                $filters,
                $range['label'],
                $from->toDateString(),
                $to->toDateString(),
                $escalatedOnly,
                $filters + ['escalated' => $escalatedOnly, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
                $request,
            );
        }

        $issues = $issuesQuery->paginate(50)->withQueryString();

        return view('reports.drilldown.asset-open-issues', [
            'issues' => $issues,
            'filters' => $filters,
            'label' => $range['label'],
            'escalatedOnly' => $escalatedOnly,
            'scopeLabel' => $this->scopeLabel($filters),
        ]);
    }

    public function protocols(Request $request): View|Response|SymfonyResponse {
        $range = $this->globalDateRange();
        [$from, $to] = $this->globalDateRangeBounds();

        $filters = $this->collectFilters($request);

        $assetIds = $this->assetIds($filters);
        $entryIds = $assetIds === []
            ? []
            : DiaryEntry::query()
            ->whereIn('asset_id', $assetIds)
            ->whereBetween('created_at', [$from, $to])
            ->pluck('id')
            ->map(static fn($v): int => (int) $v)
            ->values()
            ->all();

        $protocolsQuery = $this->defectProtocolDrilldownQuery($entryIds, $from, $to);

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            /** @var list<Protocol> $protocols */
            $protocols = $protocolsQuery->clone()->get()->all();
            $exportFilters = $filters + ['from' => $from->toDateString(), 'to' => $to->toDateString()];

            return $this->exportProtocolsCsv($protocols, $exportFilters, $from->toDateString(), $to->toDateString(), $request);
        }

        if ($request->query('export') === 'pdf') {
            /** @var list<Protocol> $protocols */
            $protocols = $protocolsQuery->clone()->get()->all();
            return $this->exportProtocolsPdf(
                $protocols,
                $filters,
                $range['label'],
                $from->toDateString(),
                $to->toDateString(),
                $filters + ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                $request,
            );
        }

        $protocols = $protocolsQuery->paginate(50)->withQueryString();

        return view('reports.drilldown.asset-protocols', [
            'protocols' => $protocols,
            'filters' => $filters,
            'label' => $range['label'],
            'scopeLabel' => $this->scopeLabel($filters),
        ]);
    }

    /**
     * @return array{customer_id:?int, category_code:?string, manufacturer:?string, model:?string, asset_id:?int, product_id:?int}
     */
    private function collectFilters(Request $request): array {
        return [
            'customer_id' => $request->filled('customer_id') ? (int) $request->integer('customer_id') : null,
            'category_code' => $request->filled('category_code') ? (string) $request->string('category_code') : null,
            'manufacturer' => $request->filled('manufacturer') ? (string) $request->string('manufacturer') : null,
            'model' => $request->filled('model') ? (string) $request->string('model') : null,
            'asset_id' => $request->filled('asset_id') ? (int) $request->integer('asset_id') : null,
            // MVP-371: Produkt-Gruppierung „Pro Modell" (typisierte Assets).
            'product_id' => $request->filled('product_id') ? (int) $request->integer('product_id') : null,
        ];
    }

    /**
     * @param  array{customer_id:?int, category_code:?string, manufacturer:?string, model:?string, asset_id:?int, product_id:?int}  $filters
     * @return array<int, int>
     */
    private function assetIds(array $filters): array {
        return Asset::query()
            ->when($filters['asset_id'] !== null, fn($q) => $q->where('id', $filters['asset_id']))
            ->when($filters['customer_id'] !== null, fn($q) => $q->where('customer_id', $filters['customer_id']))
            ->when($filters['category_code'] !== null, fn($q) => $q->where('category_code', $filters['category_code']))
            ->when($filters['manufacturer'] !== null, fn($q) => $q->where('manufacturer', $filters['manufacturer']))
            ->when($filters['model'] !== null, fn($q) => $q->where('model', $filters['model']))
            ->when($filters['product_id'] !== null, fn($q) => $q->where('product_id', $filters['product_id']))
            ->pluck('id')
            ->map(static fn($v): int => (int) $v)
            ->values()
            ->all();
    }

    /**
     * @param  array{customer_id:?int, category_code:?string, manufacturer:?string, model:?string, asset_id:?int, product_id:?int}  $filters
     */
    private function scopeLabel(array $filters): string {
        if ($filters['asset_id'] !== null) {
            $asset = Asset::query()->find($filters['asset_id']);
            if ($asset !== null) {
                return sprintf('%s — %s', (string) $asset->asset_no, (string) $asset->name);
            }
            return '#' . $filters['asset_id'];
        }
        if ($filters['product_id'] !== null) {
            $product = \App\Models\Product::query()->find($filters['product_id']);
            if ($product !== null) {
                return $product->name;
            }
            return '#' . $filters['product_id'];
        }
        if ($filters['model'] !== null || $filters['manufacturer'] !== null) {
            $value = trim(sprintf('%s %s', (string) $filters['manufacturer'], (string) $filters['model']));
            return $value === '' ? (string) __('Ohne Modell') : $value;
        }
        if ($filters['category_code'] !== null) {
            return $filters['category_code'];
        }
        return (string) __('Alle Assets');
    }

    /**
     * @param  list<OpenIssue>  $issues
     * @param  array{customer_id:?int, category_code:?string, manufacturer:?string, model:?string, asset_id:?int, product_id:?int}  $filters
     */
    private function exportOpenIssuesCsv(array $issues, array $filters, string $from, string $to, bool $escalatedOnly, Request $request): Response {
        $filename = sprintf(
            'produktanalyse-drilldown-open-issues_%s_%s%s.csv',
            $from,
            $to,
            $escalatedOnly ? '-escalated' : ''
        );
        return $this->csvWithMetadata($this->openIssueCsvRows($issues, 'AssetID'), $filename, 'assets-drilldown-open-issues', $filters, $request);
    }

    /**
     * @param  list<array{asset_id:int, asset_name:string, asset_no:string|null, total:int, by_severity:array<string,int>, recent_total:int, is_recurring:bool}>  $rows
     * @param  array{from:string, to:string}  $filters
     */
    private function exportRecurringDefectsCsv(array $rows, array $filters, string $from, string $to, Request $request): Response {
        $filename = sprintf('produktanalyse-drilldown-wiederholdefekte_%s_%s.csv', $from, $to);
        $csv = [];
        $csv[] = ['AssetID', 'Asset', 'Inventarnr', 'Defekte_Zeitraum', 'Defekte_12Monate', 'Wiederholdefekt'];
        foreach ($rows as $r) {
            $csv[] = [
                $r['asset_id'],
                $r['asset_name'],
                $r['asset_no'] ?? '',
                $r['total'],
                $r['recent_total'],
                $r['is_recurring'] ? 'ja' : 'nein',
            ];
        }

        return $this->csvWithMetadata($csv, $filename, 'assets-drilldown-recurring-defects', $filters, $request);
    }

    /**
     * @param  list<OpenIssue>  $issues
     * @param  array{customer_id:?int, category_code:?string, manufacturer:?string, model:?string, asset_id:?int, product_id:?int}  $filters
     * @param  array<string, mixed>  $auditFilters
     */
    private function exportOpenIssuesPdf(array $issues, array $filters, string $label, string $from, string $to, bool $escalatedOnly, array $auditFilters, Request $request): SymfonyResponse {
        $filename = sprintf('produktanalyse-drilldown-open-issues_%s_%s%s.pdf', $from, $to, $escalatedOnly ? '-escalated' : '');

        return $this->pdfDownload('reports.drilldown.pdf.asset-open-issues', [
            'issues' => $issues,
            'scopeLabel' => $this->scopeLabel($filters),
            'label' => $label,
            'escalatedOnly' => $escalatedOnly,
        ], $filename, request: $request, reportCode: 'assets-drilldown-open-issues', filters: $auditFilters);
    }

    /**
     * @param  list<Protocol>  $protocols
     * @param  array{customer_id:?int, category_code:?string, manufacturer:?string, model:?string, asset_id:?int, product_id:?int}  $filters
     */
    private function exportProtocolsCsv(array $protocols, array $filters, string $from, string $to, Request $request): Response {
        $filename = sprintf('produktanalyse-drilldown-defektprotokolle_%s_%s.csv', $from, $to);
        return $this->csvWithMetadata($this->protocolCsvRows($protocols), $filename, 'assets-drilldown-protocols', $filters, $request);
    }

    /**
     * @param  list<Protocol>  $protocols
     * @param  array{customer_id:?int, category_code:?string, manufacturer:?string, model:?string, asset_id:?int, product_id:?int}  $filters
     * @param  array<string, mixed>  $auditFilters
     */
    private function exportProtocolsPdf(array $protocols, array $filters, string $label, string $from, string $to, array $auditFilters, Request $request): SymfonyResponse {
        $filename = sprintf('produktanalyse-drilldown-defektprotokolle_%s_%s.pdf', $from, $to);

        return $this->pdfDownload('reports.drilldown.pdf.asset-protocols', [
            'protocols' => $protocols,
            'scopeLabel' => $this->scopeLabel($filters),
            'label' => $label,
        ], $filename, request: $request, reportCode: 'assets-drilldown-protocols', filters: $auditFilters);
    }
}
