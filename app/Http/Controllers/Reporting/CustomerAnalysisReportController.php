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
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\{AuditLog, Project, User};
use App\Services\Reporting\CustomerAnalysisReportBuilder;
use App\Support\Sqid;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CustomerAnalysisReportController extends Controller {
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function __construct(private readonly CustomerAnalysisReportBuilder $builder) {}

    public function index(Request $request): View|Response|SymfonyResponse {
        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

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
            $this->auditExport($request, 'customers-analysis', 'csv', $exportContext);

            return $this->exportCsv(array_values($rows->all()), $from->toDateString(), $to->toDateString(), $exportContext);
        }

        if ($request->query('export') === 'pdf') {
            $this->auditExport($request, 'customers-analysis', 'pdf', $exportContext);

            return $this->exportPdf(
                array_values($rows->all()),
                $range['label'],
                $from->toDateString(),
                $to->toDateString()
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
            'reportUsers' => User::query()->orderBy('name')->get(['id', 'name']),
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
    private function exportCsv(array $rows, string $from, string $to, array $filters): Response {
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
                number_format((float) $row['nonBillableShare'], 2, '.', ''),
                $row['reworkEntryCount'],
                $row['openIssueCount'],
                $row['escalationCount'],
                $row['avgEntryMinutes'],
                $row['trend30d'],
            ];
        }

        return $this->csvWithMetadata($out, $filename, 'customers-analysis', $filters);
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
     */
    private function exportPdf(array $rows, string $label, string $from, string $to): SymfonyResponse {
        $filename = sprintf('kundenanalyse_%s_%s.pdf', $from, $to);

        return Pdf::loadView('reports.pdf.customers', [
            'rows' => $rows,
            'label' => $label,
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
                'filter_hash' => $filterHash,
                'filters' => $filters,
            ],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
