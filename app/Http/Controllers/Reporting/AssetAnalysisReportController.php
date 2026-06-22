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
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\{Asset, AuditLog, Customer, User};
use App\Services\Reporting\AssetAnalysisReportBuilder;
use App\Support\Sqid;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Produkt-/Objektanalyse (MVP-041).
 *
 * Aggregiert Aufträge, offene Punkte und Defekte je Asset / Produktgruppe / Modell.
 * Vereinfachtes MVP gemäss docs/produkt-analyse.md auf Basis der vorhandenen
 * Strukturen (Asset, DiaryEntry.asset_id, OpenIssue subject, Protocol Defect).
 */
class AssetAnalysisReportController extends Controller {
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function __construct(private readonly AssetAnalysisReportBuilder $builder) {}

    public function index(Request $request): View|Response|SymfonyResponse {
        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

        $rawCustomerId = $request->query('customer_id');
        $customerId = Sqid::decodeOrNumeric(Customer::class, $rawCustomerId);
        $categoryCode = $request->filled('category_code') ? (string) $request->string('category_code') : null;
        $manufacturer = $request->filled('manufacturer') ? (string) $request->string('manufacturer') : null;
        $groupBy = (string) $request->string('group_by', 'asset');
        if (! in_array($groupBy, ['asset', 'group', 'model'], true)) {
            $groupBy = 'asset';
        }

        $rows = $this->builder->build($from, $to, $customerId, $categoryCode, $manufacturer, $groupBy);

        $exportContext = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'customer_id' => $customerId,
            'category_code' => $categoryCode,
            'manufacturer' => $manufacturer,
            'group_by' => $groupBy,
        ];

        if ($request->query('export') === 'csv') {
            $this->auditExport($request, 'assets-analysis', 'csv', $exportContext);

            return $this->exportCsv($rows, $groupBy, $from->toDateString(), $to->toDateString(), $exportContext);
        }

        if ($request->query('export') === 'pdf') {
            $this->auditExport($request, 'assets-analysis', 'pdf', $exportContext);

            return $this->exportPdf($rows, $groupBy, $range['label'], $from->toDateString(), $to->toDateString());
        }

        return view('reports.assets', [
            'rows' => $rows,
            'label' => $range['label'],
            'from' => $from,
            'to' => $to,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
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
     * @param  list<array{
     *   key:string,label:string,assetCount:int,entryCount:int,openIssueCount:int,
     *   escalationCount:int,defectCount:int,defectRate:float,lastIncidentAt:?string,
     *   drilldown:array<string,mixed>
     * }>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $rows, string $groupBy, string $from, string $to, array $filters): Response {
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
                number_format((float) $row['defectRate'], 2, '.', ''),
                $row['lastIncidentAt'] ?? '',
            ];
        }

        return $this->csvWithMetadata(
            $out,
            $filename,
            'assets-analysis',
            $filters,
        );
    }

    /**
     * @param  list<array{
     *   key:string,label:string,assetCount:int,entryCount:int,openIssueCount:int,
     *   escalationCount:int,defectCount:int,defectRate:float,lastIncidentAt:?string,
     *   drilldown:array<string,mixed>
     * }>  $rows
     */
    private function exportPdf(array $rows, string $groupBy, string $label, string $from, string $to): SymfonyResponse {
        $filename = sprintf('produktanalyse_%s_%s_%s.pdf', $groupBy, $from, $to);

        return Pdf::loadView('reports.pdf.assets', [
            'rows' => $rows,
            'label' => $label,
            'groupBy' => $groupBy,
        ])->setPaper('a4', 'landscape')->download($filename);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function auditExport(Request $request, string $reportCode, string $format, array $filters): void {
        $user = $request->user();
        if (! $user instanceof User || $user->organization_id === null) {
            return;
        }

        $filterHash = $this->reportFilterHashFull($filters);

        AuditLog::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'event' => 'report.exported',
            'auditable_type' => self::class,
            'auditable_id' => 0,
            'changes' => [
                'report_code' => $reportCode,
                'format' => $format,
                'filters' => $filters,
                'filter_hash' => $filterHash,
            ],
        ]);
    }
}
