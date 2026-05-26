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

use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\Protocol\ProtocolType;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\{Asset, AuditLog, DiaryEntry, OpenIssue, Protocol, User};
use Barryvdh\DomPDF\Facade\Pdf;
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
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function openIssues(Request $request): View|Response|SymfonyResponse {
        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

        $filters = $this->collectFilters($request);
        $escalatedOnly = $request->boolean('escalated');

        $assetIds = $this->assetIds($filters);

        $openStatuses = [
            OpenIssueStatus::Open->value,
            OpenIssueStatus::InProgress->value,
            OpenIssueStatus::Blocked->value,
            OpenIssueStatus::Reopened->value,
        ];

        $issuesQuery = OpenIssue::query()
            ->with(['assignee:id,name'])
            ->where('subject_type', Asset::class)
            ->whereIn('status', $openStatuses)
            ->when($escalatedOnly, fn($q) => $q->where('status', OpenIssueStatus::Blocked->value))
            ->when($assetIds !== [], fn($q) => $q->whereIn('subject_id', $assetIds), fn($q) => $q->whereRaw('1=0'))
            ->orderByDesc('updated_at');

        if ($request->query('export') === 'csv') {
            /** @var list<OpenIssue> $issues */
            $issues = $issuesQuery->clone()->get()->all();
            $exportFilters = $filters + ['escalated' => $escalatedOnly, 'from' => $from->toDateString(), 'to' => $to->toDateString()];
            $this->auditExport($request, 'assets-drilldown-open-issues', 'csv', $exportFilters);

            return $this->exportOpenIssuesCsv($issues, $exportFilters, $from->toDateString(), $to->toDateString(), $escalatedOnly);
        }

        if ($request->query('export') === 'pdf') {
            /** @var list<OpenIssue> $issues */
            $issues = $issuesQuery->clone()->get()->all();
            $this->auditExport($request, 'assets-drilldown-open-issues', 'pdf', $filters + ['escalated' => $escalatedOnly, 'from' => $from->toDateString(), 'to' => $to->toDateString()]);

            return $this->exportOpenIssuesPdf($issues, $filters, $range['label'], $from->toDateString(), $to->toDateString(), $escalatedOnly);
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
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

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
            $exportFilters = $filters + ['from' => $from->toDateString(), 'to' => $to->toDateString()];
            $this->auditExport($request, 'assets-drilldown-protocols', 'csv', $exportFilters);

            return $this->exportProtocolsCsv($protocols, $exportFilters, $from->toDateString(), $to->toDateString());
        }

        if ($request->query('export') === 'pdf') {
            /** @var list<Protocol> $protocols */
            $protocols = $protocolsQuery->clone()->get()->all();
            $this->auditExport($request, 'assets-drilldown-protocols', 'pdf', $filters + ['from' => $from->toDateString(), 'to' => $to->toDateString()]);

            return $this->exportProtocolsPdf($protocols, $filters, $range['label'], $from->toDateString(), $to->toDateString());
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
     * @return array{customer_id:?int, category_code:?string, manufacturer:?string, model:?string, asset_id:?int}
     */
    private function collectFilters(Request $request): array {
        return [
            'customer_id' => $request->filled('customer_id') ? (int) $request->integer('customer_id') : null,
            'category_code' => $request->filled('category_code') ? (string) $request->string('category_code') : null,
            'manufacturer' => $request->filled('manufacturer') ? (string) $request->string('manufacturer') : null,
            'model' => $request->filled('model') ? (string) $request->string('model') : null,
            'asset_id' => $request->filled('asset_id') ? (int) $request->integer('asset_id') : null,
        ];
    }

    /**
     * @param  array{customer_id:?int, category_code:?string, manufacturer:?string, model:?string, asset_id:?int}  $filters
     * @return array<int, int>
     */
    private function assetIds(array $filters): array {
        return Asset::query()
            ->when($filters['asset_id'] !== null, fn($q) => $q->where('id', $filters['asset_id']))
            ->when($filters['customer_id'] !== null, fn($q) => $q->where('customer_id', $filters['customer_id']))
            ->when($filters['category_code'] !== null, fn($q) => $q->where('category_code', $filters['category_code']))
            ->when($filters['manufacturer'] !== null, fn($q) => $q->where('manufacturer', $filters['manufacturer']))
            ->when($filters['model'] !== null, fn($q) => $q->where('model', $filters['model']))
            ->pluck('id')
            ->map(static fn($v): int => (int) $v)
            ->values()
            ->all();
    }

    /**
     * @param  array{customer_id:?int, category_code:?string, manufacturer:?string, model:?string, asset_id:?int}  $filters
     */
    private function scopeLabel(array $filters): string {
        if ($filters['asset_id'] !== null) {
            $asset = Asset::query()->find($filters['asset_id']);
            if ($asset !== null) {
                return sprintf('%s — %s', (string) $asset->asset_no, (string) $asset->name);
            }
            return '#' . $filters['asset_id'];
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
     * @param  array{customer_id:?int, category_code:?string, manufacturer:?string, model:?string, asset_id:?int}  $filters
     */
    private function exportOpenIssuesCsv(array $issues, array $filters, string $from, string $to, bool $escalatedOnly): Response {
        $filename = sprintf(
            'produktanalyse-drilldown-open-issues_%s_%s%s.csv',
            $from,
            $to,
            $escalatedOnly ? '-escalated' : ''
        );
        $rows = [];
        $rows[] = ['ID', 'AssetID', 'Titel', 'Status', 'Severity', 'Faellig', 'Zugewiesen'];
        foreach ($issues as $issue) {
            $rows[] = [
                $issue->id,
                $issue->subject_id,
                $issue->title,
                $issue->status->label(),
                $issue->severity->label(),
                $issue->due_at?->format('Y-m-d') ?? '',
                $issue->assignee?->name,
            ];
        }

        return $this->csvWithMetadata($rows, $filename, 'assets-drilldown-open-issues', $filters);
    }

    /**
     * @param  list<OpenIssue>  $issues
     * @param  array{customer_id:?int, category_code:?string, manufacturer:?string, model:?string, asset_id:?int}  $filters
     */
    private function exportOpenIssuesPdf(array $issues, array $filters, string $label, string $from, string $to, bool $escalatedOnly): SymfonyResponse {
        $filename = sprintf('produktanalyse-drilldown-open-issues_%s_%s%s.pdf', $from, $to, $escalatedOnly ? '-escalated' : '');

        return Pdf::loadView('reports.drilldown.pdf.asset-open-issues', [
            'issues' => $issues,
            'scopeLabel' => $this->scopeLabel($filters),
            'label' => $label,
            'escalatedOnly' => $escalatedOnly,
        ])->setPaper('a4')->download($filename);
    }

    /**
     * @param  list<Protocol>  $protocols
     * @param  array{customer_id:?int, category_code:?string, manufacturer:?string, model:?string, asset_id:?int}  $filters
     */
    private function exportProtocolsCsv(array $protocols, array $filters, string $from, string $to): Response {
        $filename = sprintf('produktanalyse-drilldown-defektprotokolle_%s_%s.csv', $from, $to);
        $rows = [];
        $rows[] = ['ID', 'Titel', 'Status', 'Typ', 'Zeitpunkt', 'ErstelltVon', 'AuftragID'];
        foreach ($protocols as $protocol) {
            $rows[] = [
                $protocol->id,
                $protocol->title,
                $protocol->status->label(),
                $protocol->type->label(),
                $protocol->occurred_at->format('Y-m-d H:i'),
                $protocol->creator?->name,
                $protocol->subject_id,
            ];
        }

        return $this->csvWithMetadata($rows, $filename, 'assets-drilldown-protocols', $filters);
    }

    /**
     * @param  list<Protocol>  $protocols
     * @param  array{customer_id:?int, category_code:?string, manufacturer:?string, model:?string, asset_id:?int}  $filters
     */
    private function exportProtocolsPdf(array $protocols, array $filters, string $label, string $from, string $to): SymfonyResponse {
        $filename = sprintf('produktanalyse-drilldown-defektprotokolle_%s_%s.pdf', $from, $to);

        return Pdf::loadView('reports.drilldown.pdf.asset-protocols', [
            'protocols' => $protocols,
            'scopeLabel' => $this->scopeLabel($filters),
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
