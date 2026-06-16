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

use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\Protocol\ProtocolType;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\{AuditLog, Customer, DiaryEntry, OpenIssue, Project, Protocol, TimeEntry, User};
use App\Support\Sqid;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CustomerAnalysisReportController extends Controller {
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function index(Request $request): View|Response|SymfonyResponse {
        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

        $minMinutes = max(0, (int) $request->integer('min_minutes', 0));
        $rawProjectId = $request->query('project_id');
        $projectId = Sqid::decodeOrNumeric(Project::class, $rawProjectId);
        $rawUserId = $request->query('user_id');
        $userId = Sqid::decodeOrNumeric(User::class, $rawUserId);

        $rows = collect($this->buildRows($from, $to, $projectId, $userId))
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
     * @return list<array{
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
     * }>
     */
    private function buildRows(CarbonImmutable $from, CarbonImmutable $to, ?int $projectId, ?int $userId): array {
        return array_values(Customer::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Customer $customer) use ($from, $to, $projectId, $userId): array {
                $projectIds = Project::query()
                    ->where('customer_id', $customer->id)
                    ->when($projectId !== null, fn($q) => $q->where('id', $projectId))
                    ->pluck('id')
                    ->map(static fn($v): int => (int) $v)
                    ->all();

                $entryQuery = DiaryEntry::query()
                    ->where('customer_id', $customer->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->when($projectId !== null, fn($q) => $q->where('project_id', $projectId))
                    ->when($userId !== null, fn($q) => $q->where('user_id', $userId));

                $entryIds = $entryQuery->clone()->pluck('id')->map(static fn($v): int => (int) $v)->all();
                $entryCount = count($entryIds);

                $timeQuery = TimeEntry::query()
                    ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                    ->when($projectIds !== [], fn($q) => $q->whereIn('project_id', $projectIds), fn($q) => $q->whereRaw('1=0'))
                    ->when($userId !== null, fn($q) => $q->where('user_id', $userId));

                $totalMinutes = (int) $timeQuery->clone()->sum('minutes');
                $billableMinutes = (int) $timeQuery->clone()->where('billable', true)->sum('minutes');
                $nonBillableMinutes = max(0, $totalMinutes - $billableMinutes);
                $nonBillableShare = $totalMinutes > 0
                    ? round(($nonBillableMinutes / $totalMinutes) * 100, 2)
                    : 0.0;

                $openStatuses = [
                    OpenIssueStatus::Open->value,
                    OpenIssueStatus::InProgress->value,
                    OpenIssueStatus::Blocked->value,
                    OpenIssueStatus::Reopened->value,
                ];

                $openIssuesQuery = OpenIssue::query()
                    ->whereIn('status', $openStatuses)
                    ->where(function ($q) use ($customer, $entryIds, $projectIds): void {
                        $q->where(function ($sub) use ($customer): void {
                            $sub->where('subject_type', Customer::class)
                                ->where('subject_id', $customer->id);
                        });

                        if ($entryIds !== []) {
                            $q->orWhere(function ($sub) use ($entryIds): void {
                                $sub->where('subject_type', DiaryEntry::class)
                                    ->whereIn('subject_id', $entryIds);
                            });
                        }

                        if ($projectIds !== []) {
                            $q->orWhere(function ($sub) use ($projectIds): void {
                                $sub->where('subject_type', Project::class)
                                    ->whereIn('subject_id', $projectIds);
                            });
                        }
                    });

                $openIssueCount = (int) $openIssuesQuery->clone()->count();
                $escalationCount = (int) $openIssuesQuery->clone()->where('status', OpenIssueStatus::Blocked->value)->count();

                $reworkEntryCount = (int) Protocol::query()
                    ->where('type', ProtocolType::Defect->value)
                    ->where('subject_type', DiaryEntry::class)
                    ->whereBetween('occurred_at', [$from, $to])
                    ->when($entryIds !== [], fn($q) => $q->whereIn('subject_id', $entryIds), fn($q) => $q->whereRaw('1=0'))
                    ->distinct('subject_id')
                    ->count('subject_id');

                $avgEntryMinutes = $entryCount > 0
                    ? (int) round($totalMinutes / $entryCount)
                    : 0;

                $trend30d = $this->trend30d($customer->id, $projectId, $userId, $to);

                return [
                    'customerId' => $customer->id,
                    'customerName' => $customer->name,
                    'entryCount' => $entryCount,
                    'totalMinutes' => $totalMinutes,
                    'billableMinutes' => $billableMinutes,
                    'nonBillableMinutes' => $nonBillableMinutes,
                    'nonBillableShare' => $nonBillableShare,
                    'reworkEntryCount' => $reworkEntryCount,
                    'openIssueCount' => $openIssueCount,
                    'escalationCount' => $escalationCount,
                    'avgEntryMinutes' => $avgEntryMinutes,
                    'trend30d' => $trend30d,
                ];
            })
            ->values()
            ->all());
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

    private function trend30d(int $customerId, ?int $projectId, ?int $userId, CarbonImmutable $to): int {
        $latestFrom = $to->subDays(29)->startOfDay();
        $prevFrom = $latestFrom->subDays(30)->startOfDay();
        $prevTo = $latestFrom->subSecond();

        $base = DiaryEntry::query()
            ->where('customer_id', $customerId)
            ->when($projectId !== null, fn($q) => $q->where('project_id', $projectId))
            ->when($userId !== null, fn($q) => $q->where('user_id', $userId));

        $latest = (int) $base->clone()->whereBetween('created_at', [$latestFrom, $to])->count();
        $previous = (int) $base->clone()->whereBetween('created_at', [$prevFrom, $prevTo])->count();

        return $latest - $previous;
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
