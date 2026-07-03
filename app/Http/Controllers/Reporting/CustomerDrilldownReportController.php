<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jorg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDrilldownReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\Protocol\ProtocolType;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\{Customer, DiaryEntry, OpenIssue, Project, Protocol, User};
use App\Support\Sqid;
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CustomerDrilldownReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function openIssues(Request $request): View|Response|SymfonyResponse {
        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

        $rawCustomerId = $request->query('customer_id');
        $customerId = Sqid::decodeOrNumeric(Customer::class, $rawCustomerId);
        $customerId ??= 0;

        $rawProjectId = $request->query('project_id');
        $projectId = Sqid::decodeOrNumeric(Project::class, $rawProjectId);

        $rawUserId = $request->query('user_id');
        $userId = Sqid::decodeOrNumeric(User::class, $rawUserId);

        $escalatedOnly = $request->boolean('escalated');

        $customer = $customerId > 0
            ? Customer::query()->find($customerId)
            : null;

        $projectIds = Project::query()
            ->where('customer_id', $customerId)
            ->when($projectId !== null, fn($q) => $q->where('id', $projectId))
            ->pluck('id')
            ->map(static fn($v): int => (int) $v)
            ->all();

        $entryIds = DiaryEntry::query()
            ->where('customer_id', $customerId)
            ->whereBetween('created_at', [$from, $to])
            ->when($projectId !== null, fn($q) => $q->where('project_id', $projectId))
            ->when($userId !== null, fn($q) => $q->where('user_id', $userId))
            ->pluck('id')
            ->map(static fn($v): int => (int) $v)
            ->all();

        $openStatuses = [
            OpenIssueStatus::Open->value,
            OpenIssueStatus::InProgress->value,
            OpenIssueStatus::Blocked->value,
            OpenIssueStatus::Reopened->value,
        ];

        $issuesQuery = OpenIssue::query()
            ->with(['assignee:id,name'])
            ->whereIn('status', $openStatuses)
            ->when($escalatedOnly, fn($q) => $q->where('status', OpenIssueStatus::Blocked->value))
            ->where(function ($q) use ($customerId, $entryIds, $projectIds): void {
                $q->where(function ($sub) use ($customerId): void {
                    $sub->where('subject_type', Customer::class)
                        ->where('subject_id', $customerId);
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
            })
            ->orderByDesc('updated_at');

        if ($request->query('export') === 'csv') {
            /** @var list<OpenIssue> $issues */
            $issues = $issuesQuery->clone()->get()->all();

            $this->auditExport($request, 'customer-drilldown-open-issues', 'csv', [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'customer_id' => $customerId,
                'project_id' => $projectId,
                'user_id' => $userId,
                'escalated' => $escalatedOnly,
            ]);

            return $this->exportOpenIssuesCsv($issues, $customerId, $from->toDateString(), $to->toDateString(), $escalatedOnly, [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'customer_id' => $customerId,
                'project_id' => $projectId,
                'user_id' => $userId,
                'escalated' => $escalatedOnly,
            ]);
        }

        if ($request->query('export') === 'pdf') {
            /** @var list<OpenIssue> $issues */
            $issues = $issuesQuery->clone()->get()->all();

            $this->auditExport($request, 'customer-drilldown-open-issues', 'pdf', [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'customer_id' => $customerId,
                'project_id' => $projectId,
                'user_id' => $userId,
                'escalated' => $escalatedOnly,
            ]);

            return $this->exportOpenIssuesPdf(
                $issues,
                ($customer !== null ? $customer->name : null) ?? ('#' . $customerId),
                $range['label'],
                $customerId,
                $from->toDateString(),
                $to->toDateString(),
                $escalatedOnly
            );
        }

        $issues = $issuesQuery->paginate(50)->withQueryString();

        /** @var view-string $openIssuesView */
        $openIssuesView = 'reports.drilldown.customer-open-issues';

        return view($openIssuesView, [
            'issues' => $issues,
            'customer' => $customer,
            'label' => $range['label'],
            'customerId' => $customerId,
            'projectId' => $projectId,
            'userId' => $userId,
            'escalatedOnly' => $escalatedOnly,
        ]);
    }

    public function protocols(Request $request): View|Response|SymfonyResponse {
        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

        $rawCustomerId = $request->query('customer_id');
        $customerId = Sqid::decodeOrNumeric(Customer::class, $rawCustomerId);
        $customerId ??= 0;

        $rawProjectId = $request->query('project_id');
        $projectId = Sqid::decodeOrNumeric(Project::class, $rawProjectId);

        $rawUserId = $request->query('user_id');
        $userId = Sqid::decodeOrNumeric(User::class, $rawUserId);

        $customer = $customerId > 0
            ? Customer::query()->find($customerId)
            : null;

        $entryIds = DiaryEntry::query()
            ->where('customer_id', $customerId)
            ->whereBetween('created_at', [$from, $to])
            ->when($projectId !== null, fn($q) => $q->where('project_id', $projectId))
            ->when($userId !== null, fn($q) => $q->where('user_id', $userId))
            ->pluck('id')
            ->map(static fn($v): int => (int) $v)
            ->all();

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

            $this->auditExport($request, 'customer-drilldown-protocols', 'csv', [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'customer_id' => $customerId,
                'project_id' => $projectId,
                'user_id' => $userId,
            ]);

            return $this->exportProtocolsCsv($protocols, $customerId, $from->toDateString(), $to->toDateString(), [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'customer_id' => $customerId,
                'project_id' => $projectId,
                'user_id' => $userId,
            ]);
        }

        if ($request->query('export') === 'pdf') {
            /** @var list<Protocol> $protocols */
            $protocols = $protocolsQuery->clone()->get()->all();

            $this->auditExport($request, 'customer-drilldown-protocols', 'pdf', [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'customer_id' => $customerId,
                'project_id' => $projectId,
                'user_id' => $userId,
            ]);

            return $this->exportProtocolsPdf(
                $protocols,
                ($customer !== null ? $customer->name : null) ?? ('#' . $customerId),
                $range['label'],
                $customerId,
                $from->toDateString(),
                $to->toDateString()
            );
        }

        $protocols = $protocolsQuery->paginate(50)->withQueryString();

        /** @var view-string $protocolsView */
        $protocolsView = 'reports.drilldown.customer-protocols';

        return view($protocolsView, [
            'protocols' => $protocols,
            'customer' => $customer,
            'label' => $range['label'],
            'customerId' => $customerId,
            'projectId' => $projectId,
            'userId' => $userId,
        ]);
    }

    /**
     * @param  array<int, OpenIssue>    $issues
     * @param  array<string, mixed>      $filters
     */
    private function exportOpenIssuesCsv(
        array $issues,
        int $customerId,
        string $from,
        string $to,
        bool $escalatedOnly,
        array $filters,
    ): Response {
        $filename = sprintf(
            'kunden-drilldown-open-issues-%d-%s-%s%s.csv',
            $customerId,
            $from,
            $to,
            $escalatedOnly ? '-escalated' : ''
        );

        $out = [];
        $out[] = ['ID', 'Titel', 'Status', 'Severity', 'Fällig', 'Zugewiesen'];
        foreach ($issues as $issue) {
            /** @var OpenIssue $issue */
            $out[] = [
                $issue->id,
                $issue->title,
                $issue->status->label(),
                $issue->severity->label(),
                $issue->due_at?->format('Y-m-d') ?? '',
                $issue->assignee ? $issue->assignee->name : '',
            ];
        }

        return $this->csvWithMetadata($out, $filename, 'customer-drilldown-open-issues', $filters);
    }

    /**
     * @param  array<int, OpenIssue>  $issues
     */
    private function exportOpenIssuesPdf(
        array $issues,
        string $customerName,
        string $label,
        int $customerId,
        string $from,
        string $to,
        bool $escalatedOnly,
    ): SymfonyResponse {
        $filename = sprintf(
            'kunden-drilldown-open-issues-%d-%s-%s%s.pdf',
            $customerId,
            $from,
            $to,
            $escalatedOnly ? '-escalated' : ''
        );

        return $this->pdfDownload('reports.drilldown.pdf.customer-open-issues', [
            'issues' => $issues,
            'customerName' => $customerName,
            'label' => $label,
            'escalatedOnly' => $escalatedOnly,
        ], $filename);
    }

    /**
     * @param  array<int, Protocol>    $protocols
     * @param  array<string, mixed>    $filters
     */
    private function exportProtocolsCsv(
        array $protocols,
        int $customerId,
        string $from,
        string $to,
        array $filters,
    ): Response {
        $filename = sprintf('kunden-drilldown-defektprotokolle-%d-%s-%s.csv', $customerId, $from, $to);

        $out = [];
        $out[] = ['ID', 'Titel', 'Status', 'Typ', 'Zeitpunkt', 'ErstelltVon', 'AuftragID'];
        foreach ($protocols as $protocol) {
            /** @var Protocol $protocol */
            $out[] = [
                $protocol->id,
                $protocol->title,
                $protocol->status->label(),
                $protocol->type->label(),
                $protocol->occurred_at->format('Y-m-d H:i'),
                $protocol->creator ? $protocol->creator->name : '',
                $protocol->subject_id,
            ];
        }

        return $this->csvWithMetadata($out, $filename, 'customer-drilldown-protocols', $filters);
    }

    /**
     * @param  array<int, Protocol>  $protocols
     */
    private function exportProtocolsPdf(
        array $protocols,
        string $customerName,
        string $label,
        int $customerId,
        string $from,
        string $to,
    ): SymfonyResponse {
        $filename = sprintf('kunden-drilldown-defektprotokolle-%d-%s-%s.pdf', $customerId, $from, $to);

        return $this->pdfDownload('reports.drilldown.pdf.customer-protocols', [
            'protocols' => $protocols,
            'customerName' => $customerName,
            'label' => $label,
        ], $filename);
    }
}
