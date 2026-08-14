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

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{BuildsOpenIssueDrilldown, RendersReportPdf, WritesReportCsv};
use App\Models\{Customer, DiaryEntry, OpenIssue, Project, Protocol, User};
use App\Support\Sqid;
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CustomerDrilldownReportController extends Controller {
    use BuildsOpenIssueDrilldown;
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function openIssues(Request $request): View|Response|SymfonyResponse {
        $range = $this->globalDateRange();
        [$from, $to] = $this->globalDateRangeBounds();

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

        // Kunden-Spezifikum: OR-Zweige über Kunde selbst, Aufträge und Projekte.
        $issuesQuery = $this->openIssueDrilldownQuery($escalatedOnly, function ($query) use ($customerId, $entryIds, $projectIds): void {
            $query->where(function ($q) use ($customerId, $entryIds, $projectIds): void {
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
            });
        });

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            /** @var list<OpenIssue> $issues */
            $issues = $issuesQuery->clone()->get()->all();

            return $this->exportOpenIssuesCsv($issues, $customerId, $from->toDateString(), $to->toDateString(), $escalatedOnly, [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'customer_id' => $customerId,
                'project_id' => $projectId,
                'user_id' => $userId,
                'escalated' => $escalatedOnly,
            ], $request);
        }

        if ($request->query('export') === 'pdf') {
            /** @var list<OpenIssue> $issues */
            $issues = $issuesQuery->clone()->get()->all();

            return $this->exportOpenIssuesPdf(
                $issues,
                ($customer !== null ? $customer->name : null) ?? ('#' . $customerId),
                $range['label'],
                $customerId,
                [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    'customer_id' => $customerId,
                    'project_id' => $projectId,
                    'user_id' => $userId,
                    'escalated' => $escalatedOnly,
                ],
                $request,
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
        [$from, $to] = $this->globalDateRangeBounds();

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

        $protocolsQuery = $this->defectProtocolDrilldownQuery($entryIds, $from, $to);

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            /** @var list<Protocol> $protocols */
            $protocols = $protocolsQuery->clone()->get()->all();

            return $this->exportProtocolsCsv($protocols, $customerId, $from->toDateString(), $to->toDateString(), [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'customer_id' => $customerId,
                'project_id' => $projectId,
                'user_id' => $userId,
            ], $request);
        }

        if ($request->query('export') === 'pdf') {
            /** @var list<Protocol> $protocols */
            $protocols = $protocolsQuery->clone()->get()->all();

            return $this->exportProtocolsPdf(
                $protocols,
                ($customer !== null ? $customer->name : null) ?? ('#' . $customerId),
                $range['label'],
                $customerId,
                [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    'customer_id' => $customerId,
                    'project_id' => $projectId,
                    'user_id' => $userId,
                ],
                $request,
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
     * @param  list<OpenIssue>       $issues
     * @param  array<string, mixed>  $filters
     */
    private function exportOpenIssuesCsv(
        array $issues,
        int $customerId,
        string $from,
        string $to,
        bool $escalatedOnly,
        array $filters,
        Request $request,
    ): Response {
        $filename = sprintf(
            'kunden-drilldown-open-issues-%d-%s-%s%s.csv',
            $customerId,
            $from,
            $to,
            $escalatedOnly ? '-escalated' : ''
        );

        return $this->csvWithMetadata($this->openIssueCsvRows($issues), $filename, 'customer-drilldown-open-issues', $filters, $request);
    }

    /**
     * @param  list<OpenIssue>       $issues
     * @param  array<string, mixed>  $filters
     */
    private function exportOpenIssuesPdf(
        array $issues,
        string $customerName,
        string $label,
        int $customerId,
        array $filters,
        Request $request,
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
        ], $filename, request: $request, reportCode: 'customer-drilldown-open-issues', filters: $filters);
    }

    /**
     * @param  list<Protocol>        $protocols
     * @param  array<string, mixed>  $filters
     */
    private function exportProtocolsCsv(
        array $protocols,
        int $customerId,
        string $from,
        string $to,
        array $filters,
        Request $request,
    ): Response {
        $filename = sprintf('kunden-drilldown-defektprotokolle-%d-%s-%s.csv', $customerId, $from, $to);

        return $this->csvWithMetadata($this->protocolCsvRows($protocols), $filename, 'customer-drilldown-protocols', $filters, $request);
    }

    /**
     * @param  list<Protocol>        $protocols
     * @param  array<string, mixed>  $filters
     */
    private function exportProtocolsPdf(
        array $protocols,
        string $customerName,
        string $label,
        int $customerId,
        array $filters,
        Request $request,
        string $from,
        string $to,
    ): SymfonyResponse {
        $filename = sprintf('kunden-drilldown-defektprotokolle-%d-%s-%s.pdf', $customerId, $from, $to);

        return $this->pdfDownload('reports.drilldown.pdf.customer-protocols', [
            'protocols' => $protocols,
            'customerName' => $customerName,
            'label' => $label,
        ], $filename, request: $request, reportCode: 'customer-drilldown-protocols', filters: $filters);
    }
}
