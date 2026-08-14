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

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{BuildsOpenIssueDrilldown, RendersReportPdf, WritesReportCsv};
use App\Models\{DiaryEntry, EntryType, OpenIssue, Protocol};
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EntryTypeDrilldownReportController extends Controller {
    use BuildsOpenIssueDrilldown;
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function openIssues(Request $request): View|Response|SymfonyResponse {
        $range = $this->globalDateRange();
        [$from, $to] = $this->globalDateRangeBounds();

        $entryTypeId = (int) $request->integer('entry_type_id');
        $customerId = $request->filled('customer_id') ? (int) $request->integer('customer_id') : null;
        $userId = $request->filled('user_id') ? (int) $request->integer('user_id') : null;
        $statusFilter = $request->filled('status') ? (int) $request->integer('status') : null;
        $escalatedOnly = $request->boolean('escalated');

        $entryType = $entryTypeId > 0
            ? EntryType::query()->find($entryTypeId)
            : null;

        $entryIds = $this->entryIds($from->toDateTimeString(), $to->toDateTimeString(), $entryTypeId, $customerId, $userId, $statusFilter);

        $issuesQuery = $this->openIssueDrilldownQuery($escalatedOnly, function ($query) use ($entryIds): void {
            $query->where('subject_type', DiaryEntry::class)
                ->when($entryIds !== [], fn($q) => $q->whereIn('subject_id', $entryIds), fn($q) => $q->whereRaw('1=0'));
        });

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
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

            return $this->exportOpenIssuesCsv($issues, $entryTypeId, $from->toDateString(), $to->toDateString(), $escalatedOnly, $exportFilters, $request);
        }

        if ($request->query('export') === 'pdf') {
            /** @var list<OpenIssue> $issues */
            $issues = $issuesQuery->clone()->get()->all();

            return $this->exportOpenIssuesPdf(
                $issues,
                ($entryType !== null ? $entryType->label : null) ?? ('#' . $entryTypeId),
                $range['label'],
                $entryTypeId,
                [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    'entry_type_id' => $entryTypeId,
                    'customer_id' => $customerId,
                    'user_id' => $userId,
                    'status' => $statusFilter,
                    'escalated' => $escalatedOnly,
                ],
                $request,
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
        [$from, $to] = $this->globalDateRangeBounds();

        $entryTypeId = (int) $request->integer('entry_type_id');
        $customerId = $request->filled('customer_id') ? (int) $request->integer('customer_id') : null;
        $userId = $request->filled('user_id') ? (int) $request->integer('user_id') : null;
        $statusFilter = $request->filled('status') ? (int) $request->integer('status') : null;

        $entryType = $entryTypeId > 0
            ? EntryType::query()->find($entryTypeId)
            : null;

        $entryIds = $this->entryIds($from->toDateTimeString(), $to->toDateTimeString(), $entryTypeId, $customerId, $userId, $statusFilter);

        $protocolsQuery = $this->defectProtocolDrilldownQuery($entryIds, $from, $to);

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
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

            return $this->exportProtocolsCsv($protocols, $entryTypeId, $from->toDateString(), $to->toDateString(), $exportFilters, $request);
        }

        if ($request->query('export') === 'pdf') {
            /** @var list<Protocol> $protocols */
            $protocols = $protocolsQuery->clone()->get()->all();

            return $this->exportProtocolsPdf(
                $protocols,
                ($entryType !== null ? $entryType->label : null) ?? ('#' . $entryTypeId),
                $range['label'],
                $entryTypeId,
                [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    'entry_type_id' => $entryTypeId,
                    'customer_id' => $customerId,
                    'user_id' => $userId,
                    'status' => $statusFilter,
                ],
                $request,
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
    private function exportOpenIssuesCsv(array $issues, int $entryTypeId, string $from, string $to, bool $escalatedOnly, array $filters, Request $request): Response {
        $filename = sprintf(
            'auftragstyp-drilldown-open-issues-%d-%s-%s%s.csv',
            $entryTypeId,
            $from,
            $to,
            $escalatedOnly ? '-escalated' : ''
        );

        return $this->csvWithMetadata($this->openIssueCsvRows($issues), $filename, 'entry-type-drilldown-open-issues', $filters, $request);
    }

    /**
     * @param  list<OpenIssue>       $issues
     * @param  array<string, mixed>  $filters
     */
    private function exportOpenIssuesPdf(array $issues, string $entryTypeLabel, string $label, int $entryTypeId, array $filters, Request $request, string $from, string $to, bool $escalatedOnly): SymfonyResponse {
        $filename = sprintf(
            'auftragstyp-drilldown-open-issues-%d-%s-%s%s.pdf',
            $entryTypeId,
            $from,
            $to,
            $escalatedOnly ? '-escalated' : ''
        );

        return $this->pdfDownload('reports.drilldown.pdf.entry-type-open-issues', [
            'issues' => $issues,
            'entryTypeLabel' => $entryTypeLabel,
            'label' => $label,
            'escalatedOnly' => $escalatedOnly,
        ], $filename, request: $request, reportCode: 'entry-type-drilldown-open-issues', filters: $filters);
    }

    /**
     * @param  list<Protocol>           $protocols
     * @param  array<string, mixed>     $filters
     */
    private function exportProtocolsCsv(array $protocols, int $entryTypeId, string $from, string $to, array $filters, Request $request): Response {
        $filename = sprintf('auftragstyp-drilldown-defektprotokolle-%d-%s-%s.csv', $entryTypeId, $from, $to);

        return $this->csvWithMetadata($this->protocolCsvRows($protocols), $filename, 'entry-type-drilldown-protocols', $filters, $request);
    }

    /**
     * @param  list<Protocol>        $protocols
     * @param  array<string, mixed>  $filters
     */
    private function exportProtocolsPdf(array $protocols, string $entryTypeLabel, string $label, int $entryTypeId, array $filters, Request $request, string $from, string $to): SymfonyResponse {
        $filename = sprintf('auftragstyp-drilldown-defektprotokolle-%d-%s-%s.pdf', $entryTypeId, $from, $to);

        return $this->pdfDownload('reports.drilldown.pdf.entry-type-protocols', [
            'protocols' => $protocols,
            'entryTypeLabel' => $entryTypeLabel,
            'label' => $label,
        ], $filename, request: $request, reportCode: 'entry-type-drilldown-protocols', filters: $filters);
    }
}
