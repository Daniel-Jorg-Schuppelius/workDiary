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
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\{Project, User};
use App\Services\Reporting\CustomerAnalysisReportBuilder;
use App\Support\Sqid;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CustomerAnalysisReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function __construct(private readonly CustomerAnalysisReportBuilder $builder) {}

    public function index(Request $request): View|Response|SymfonyResponse {
        $range = $this->globalDateRange();
        [$from, $to] = $this->globalDateRangeBounds();

        $minMinutes = max(0, (int) $request->integer('min_minutes', 0));
        $rawProjectId = $request->query('project_id');
        $projectId = Sqid::decodeOrNumeric(Project::class, $rawProjectId);
        $rawUserId = $request->query('user_id');
        $userId = Sqid::decodeOrNumeric(User::class, $rawUserId);

        $rows = collect($this->builder->build($from, $to, $projectId, $userId))
            ->filter(static fn(array $row): bool => $row['totalMinutes'] >= $minMinutes)
            ->values();

        $exportContext = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'min_minutes' => $minMinutes,
            'project_id' => $projectId,
            'user_id' => $userId,
        ];

        if ($request->query('export') === 'csv') {
            return $this->exportCsv(array_values($rows->all()), $from->toDateString(), $to->toDateString(), $exportContext, $request);
        }

        if ($request->query('export') === 'pdf') {
            return $this->exportPdf(
                array_values($rows->all()),
                $range['label'],
                $from->toDateString(),
                $to->toDateString(),
                $exportContext,
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
            'label' => $range['label'],
            'minMinutes' => $minMinutes,
            'projectId' => $projectId,
            'userId' => $userId,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            // Mandantengrenze: User hat KEINEN globalen OrganizationScope — ohne expliziten
            // Org-Filter listete das Dropdown User aller Orgs (Tenant-Leak, Bauturbo A17, ReportPdfTenantTest).
            'reportUsers' => User::query()
                ->where('organization_id', Auth::user()?->organization_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'topByMinutes' => $topByMinutes,
            'topByRework' => $topByRework,
            'topByNonBillable' => $topByNonBillable,
        ]);
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
     * @param  array<string, mixed>  $filters
     */
    private function exportPdf(array $rows, string $label, string $from, string $to, array $filters, Request $request): SymfonyResponse {
        $filename = sprintf('kundenanalyse_%s_%s.pdf', $from, $to);

        return $this->pdfDownload('reports.pdf.customers', [
            'rows' => $rows,
            'label' => $label,
        ], $filename, 'landscape', $request, 'customers-analysis', $filters);
    }
}
