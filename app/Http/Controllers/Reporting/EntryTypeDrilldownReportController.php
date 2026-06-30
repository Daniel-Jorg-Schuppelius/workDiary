<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jorg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntryTypeDrilldownReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\Protocol\ProtocolType;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\{AuditLog, DiaryEntry, EntryType, OpenIssue, Protocol, User};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EntryTypeDrilldownReportController extends Controller {
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function openIssues(Request $request): View|Response|SymfonyResponse {
        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

        $entryTypeId = (int) $request->integer('entry_type_id');
        $customerId = $request->filled('customer_id') ? (int) $request->integer('customer_id') : null;
        $userId = $request->filled('user_id') ? (int) $request->integer('user_id') : null;
        $statusFilter = $request->filled('status') ? (int) $request->integer('status') : null;
        $escalatedOnly = $request->boolean('escalated');

        $entryType = $entryTypeId > 0
            ? EntryType::query()->find($entryTypeId)
            : null;

        $entryIds = $this->entryIds($from->toDateTimeString(), $to->toDateTimeString(), $entryTypeId, $customerId, $userId, $statusFilter);

        $openStatuses = [
            OpenIssueStatus::Open->value,
            OpenIssueStatus::InProgress->value,
            OpenIssueStatus::Blocked->value,
            OpenIssueStatus::Reopened->value,
        ];

        $issuesQuery = OpenIssue::query()
            ->with(['assignee:id,name'])
            ->whereIn('status', $openStatuses)
            ->where('subject_type', DiaryEntry::class)
            ->when($escalatedOnly, fn($q) => $q->where('status', OpenIssueStatus::Blocked->value))
            ->when($entryIds !== [], fn($q) => $q->whereIn('subject_id', $entryIds), fn($q) => $q->whereRaw('1=0'))
            ->orderByDesc('updated_at');

        if ($request->query('export') === 'csv') {
            /** @var list<OpenIssue> $issues */
            $issues = $issuesQuery->clone()->get()->all();

            $exportFilters = [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'entry_type_id' => $entryTypeId,
                'customer_id' => $customerId,
                'user_id' => $userId,
                'status' => $statusFilter,
                'escalated' => $escalatedOnly,
            ];
            $this->auditExport($request, 'entry-type-drilldown-open-issues', 'csv', $exportFilters);

            return $this->exportOpenIssuesCsv($issues, $entryTypeId, $from->toDateString(), $to->toDateString(), $escalatedOnly, $exportFilters);
        }

        if ($request->query('export') === 'pdf') {
            /** @var list<OpenIssue> $issues */
            $issues = $issuesQuery->clone()->get()->all();

            $this->auditExport($request, 'entry-type-drilldown-open-issues', 'pdf', [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'entry_type_id' => $entryTypeId,
                'customer_id' => $customerId,
                'user_id' => $userId,
                'status' => $statusFilter,
                'escalated' => $escalatedOnly,
            ]);

            return $this->exportOpenIssuesPdf(
                $issues,
                ($entryType !== null ? $entryType->label : null) ?? ('#' . $entryTypeId),
                $range['label'],
                $entryTypeId,
                $from->toDateString(),
                $to->toDateString(),
                $escalatedOnly
            );
        }

        $issues = $issuesQuery->paginate(50)->withQueryString();

        return view('reports.drilldown.entry-type-open-issues', [
            'issues' => $issues,
            'entryType' => $entryType,
            'label' => $range['label'],
            'entryTypeId' => $entryTypeId,
            'customerId' => $customerId,
            'userId' => $userId,
            'statusFilter' => $statusFilter,
            'escalatedOnly' => $escalatedOnly,
        ]);
    }

    public function protocols(Request $request): View|Response|SymfonyResponse {
        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

        $entryTypeId = (int) $request->integer('entry_type_id');
        $customerId = $request->filled('customer_id') ? (int) $request->integer('customer_id') : null;
        $userId = $request->filled('user_id') ? (int) $request->integer('user_id') : null;
        $statusFilter = $request->filled('status') ? (int) $request->integer('status') : null;

        $entryType = $entryTypeId > 0
            ? EntryType::query()->find($entryTypeId)
            : null;

        $entryIds = $this->entryIds($from->toDateTimeString(), $to->toDateTimeString(), $entryTypeId, $customerId, $userId, $statusFilter);

        $protocolsQuery = Protocol::query()
            ->with(['creator:id,name'])
            ->where('type', ProtocolType::Defect->value)
            ->where('subject_type', DiaryEntry::class)
            ->whereBetween('occurred_at', [$from, $to])
            ->when($entryIds !== [], fn($q) => $q->whereIn('subject_id', $entryIds), fn($q) => $q->whereRaw('1=0'))
            ->orderByDesc('occurred_at');

        if ($request->query('export') === 'csv') {
            /** @var list<Protocol> $protocols */
            $protocols = $protocolsQuery->clone()->get()->all();

            $exportFilters = [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'entry_type_id' => $entryTypeId,
                'customer_id' => $customerId,
                'user_id' => $userId,
                'status' => $statusFilter,
            ];
            $this->auditExport($request, 'entry-type-drilldown-protocols', 'csv', $exportFilters);

            return $this->exportProtocolsCsv($protocols, $entryTypeId, $from->toDateString(), $to->toDateString(), $exportFilters);
        }

        if ($request->query('export') === 'pdf') {
            /** @var list<Protocol> $protocols */
            $protocols = $protocolsQuery->clone()->get()->all();

            $this->auditExport($request, 'entry-type-drilldown-protocols', 'pdf', [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'entry_type_id' => $entryTypeId,
                'customer_id' => $customerId,
                'user_id' => $userId,
                'status' => $statusFilter,
            ]);

            return $this->exportProtocolsPdf(
                $protocols,
                ($entryType !== null ? $entryType->label : null) ?? ('#' . $entryTypeId),
                $range['label'],
                $entryTypeId,
                $from->toDateString(),
                $to->toDateString()
            );
        }

        $protocols = $protocolsQuery->paginate(50)->withQueryString();

        return view('reports.drilldown.entry-type-protocols', [
            'protocols' => $protocols,
            'entryType' => $entryType,
            'label' => $range['label'],
            'entryTypeId' => $entryTypeId,
            'customerId' => $customerId,
            'userId' => $userId,
            'statusFilter' => $statusFilter,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function entryIds(string $from, string $to, int $entryTypeId, ?int $customerId, ?int $userId, ?int $statusFilter): array {
        return DiaryEntry::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($entryTypeId > 0, fn($q) => $q->where('entry_type_id', $entryTypeId))
            ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
            ->when($userId !== null, fn($q) => $q->where('user_id', $userId))
            ->when($statusFilter !== null, fn($q) => $q->where('status', $statusFilter))
            ->pluck('id')
            ->map(static fn($v): int => (int) $v)
            ->values()
            ->all();
    }

    /**
     * @param  list<OpenIssue>          $issues
     * @param  array<string, mixed>     $filters
     */
    private function exportOpenIssuesCsv(array $issues, int $entryTypeId, string $from, string $to, bool $escalatedOnly, array $filters): Response {
        $filename = sprintf(
            'auftragstyp-drilldown-open-issues-%d-%s-%s%s.csv',
            $entryTypeId,
            $from,
            $to,
            $escalatedOnly ? '-escalated' : ''
        );

        $rows = [];
        $rows[] = ['ID', 'Titel', 'Status', 'Severity', 'Fällig', 'Zugewiesen'];
        foreach ($issues as $issue) {
            $rows[] = [
                $issue->id,
                $issue->title,
                $issue->status->label(),
                $issue->severity->label(),
                $issue->due_at?->format('Y-m-d') ?? '',
                $issue->assignee ? $issue->assignee->name : '',
            ];
        }

        return $this->csvWithMetadata($rows, $filename, 'entry-type-drilldown-open-issues', $filters);
    }

    /**
     * @param  list<OpenIssue>  $issues
     */
    private function exportOpenIssuesPdf(array $issues, string $entryTypeLabel, string $label, int $entryTypeId, string $from, string $to, bool $escalatedOnly): SymfonyResponse {
        $filename = sprintf(
            'auftragstyp-drilldown-open-issues-%d-%s-%s%s.pdf',
            $entryTypeId,
            $from,
            $to,
            $escalatedOnly ? '-escalated' : ''
        );

        return Pdf::loadView('reports.drilldown.pdf.entry-type-open-issues', [
            'issues' => $issues,
            'entryTypeLabel' => $entryTypeLabel,
            'label' => $label,
            'escalatedOnly' => $escalatedOnly,
        ])->setPaper('a4')->download($filename);
    }

    /**
     * @param  list<Protocol>           $protocols
     * @param  array<string, mixed>     $filters
     */
    private function exportProtocolsCsv(array $protocols, int $entryTypeId, string $from, string $to, array $filters): Response {
        $filename = sprintf('auftragstyp-drilldown-defektprotokolle-%d-%s-%s.csv', $entryTypeId, $from, $to);

        $rows = [];
        $rows[] = ['ID', 'Titel', 'Status', 'Typ', 'Zeitpunkt', 'ErstelltVon', 'AuftragID'];
        foreach ($protocols as $protocol) {
            $rows[] = [
                $protocol->id,
                $protocol->title,
                $protocol->status->label(),
                $protocol->type->label(),
                $protocol->occurred_at->format('Y-m-d H:i'),
                $protocol->creator ? $protocol->creator->name : '',
                $protocol->subject_id,
            ];
        }

        return $this->csvWithMetadata($rows, $filename, 'entry-type-drilldown-protocols', $filters);
    }

    /**
     * @param  list<Protocol>  $protocols
     */
    private function exportProtocolsPdf(array $protocols, string $entryTypeLabel, string $label, int $entryTypeId, string $from, string $to): SymfonyResponse {
        $filename = sprintf('auftragstyp-drilldown-defektprotokolle-%d-%s-%s.pdf', $entryTypeId, $from, $to);

        return Pdf::loadView('reports.drilldown.pdf.entry-type-protocols', [
            'protocols' => $protocols,
            'entryTypeLabel' => $entryTypeLabel,
            'label' => $label,
        ])->setPaper('a4')->download($filename);
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
