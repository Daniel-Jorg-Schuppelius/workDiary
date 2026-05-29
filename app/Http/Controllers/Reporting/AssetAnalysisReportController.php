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

use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\Protocol\ProtocolType;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\{Asset, AuditLog, Customer, DiaryEntry, OpenIssue, Protocol, User};
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
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

    public function index(Request $request): View|Response|SymfonyResponse {
        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

        $customerId = sqid_decode(Customer::class, $request->query('customer_id'));
        $categoryCode = $request->filled('category_code') ? (string) $request->string('category_code') : null;
        $manufacturer = $request->filled('manufacturer') ? (string) $request->string('manufacturer') : null;
        $groupBy = (string) $request->string('group_by', 'asset');
        if (! in_array($groupBy, ['asset', 'group', 'model'], true)) {
            $groupBy = 'asset';
        }

        $rows = $this->buildRows($from, $to, $customerId, $categoryCode, $manufacturer, $groupBy);

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
     * @return list<array{
     *   key:string,
     *   label:string,
     *   assetCount:int,
     *   entryCount:int,
     *   openIssueCount:int,
     *   escalationCount:int,
     *   defectCount:int,
     *   defectRate:float,
     *   lastIncidentAt:?string,
     *   drilldown:array<string,mixed>
     * }>
     */
    private function buildRows(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $customerId,
        ?string $categoryCode,
        ?string $manufacturer,
        string $groupBy,
    ): array {
        $assets = Asset::query()
            ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
            ->when($categoryCode !== null, fn($q) => $q->where('category_code', $categoryCode))
            ->when($manufacturer !== null, fn($q) => $q->where('manufacturer', $manufacturer))
            ->get(['id', 'name', 'asset_no', 'category_code', 'manufacturer', 'model']);

        if ($assets->isEmpty()) {
            return [];
        }

        /** @var list<int> $assetIds */
        $assetIds = $assets->pluck('id')->map(static fn($v): int => (int) $v)->values()->all();

        $entryRows = DiaryEntry::query()
            ->whereIn('asset_id', $assetIds)
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'asset_id']);

        /** @var array<int, list<int>> $entriesByAsset */
        $entriesByAsset = [];
        $allEntryIds = [];
        foreach ($entryRows as $entry) {
            $aid = (int) $entry->asset_id;
            $entriesByAsset[$aid][] = (int) $entry->id;
            $allEntryIds[] = (int) $entry->id;
        }

        $openStatuses = [
            OpenIssueStatus::Open->value,
            OpenIssueStatus::InProgress->value,
            OpenIssueStatus::Blocked->value,
            OpenIssueStatus::Reopened->value,
        ];

        /** @var array<int, int> $openByAsset */
        $openByAsset = OpenIssue::query()
            ->where('subject_type', Asset::class)
            ->whereIn('subject_id', $assetIds)
            ->whereIn('status', $openStatuses)
            ->selectRaw('subject_id as aid, COUNT(*) as c')
            ->groupBy('subject_id')
            ->pluck('c', 'aid')
            ->map(static fn($v): int => (int) $v)
            ->all();

        /** @var array<int, int> $escByAsset */
        $escByAsset = OpenIssue::query()
            ->where('subject_type', Asset::class)
            ->whereIn('subject_id', $assetIds)
            ->where('status', OpenIssueStatus::Blocked->value)
            ->selectRaw('subject_id as aid, COUNT(*) as c')
            ->groupBy('subject_id')
            ->pluck('c', 'aid')
            ->map(static fn($v): int => (int) $v)
            ->all();

        /** @var array<int, int> $defectByEntry */
        $defectByEntry = [];
        /** @var array<int, ?string> $lastDefectAtByEntry */
        $lastDefectAtByEntry = [];
        if ($allEntryIds !== []) {
            $defects = Protocol::query()
                ->where('subject_type', DiaryEntry::class)
                ->where('type', ProtocolType::Defect->value)
                ->whereIn('subject_id', $allEntryIds)
                ->whereBetween('occurred_at', [$from, $to])
                ->selectRaw('subject_id as eid, COUNT(*) as c, MAX(occurred_at) as last_at')
                ->groupBy('subject_id')
                ->get();
            foreach ($defects as $d) {
                /** @var object{eid:int|string, c:int|string, last_at:?string} $d */
                $eid = (int) $d->eid;
                $defectByEntry[$eid] = (int) $d->c;
                $lastDefectAtByEntry[$eid] = $d->last_at;
            }
        }

        /** @var array<int, array{defects:int, last:?string}> $defectByAsset */
        $defectByAsset = [];
        foreach ($entriesByAsset as $aid => $eids) {
            $count = 0;
            $last = null;
            foreach ($eids as $eid) {
                $count += $defectByEntry[$eid] ?? 0;
                $candidate = $lastDefectAtByEntry[$eid] ?? null;
                if ($candidate !== null && ($last === null || $candidate > $last)) {
                    $last = $candidate;
                }
            }
            $defectByAsset[$aid] = ['defects' => $count, 'last' => $last];
        }

        /** @var array<string, array{label:string, assetIds:list<int>, drilldown:array<string,mixed>}> $groups */
        $groups = [];
        foreach ($assets as $asset) {
            [$key, $label, $drilldown] = match ($groupBy) {
                'group' => [
                    (string) ($asset->category_code ?? '_none_'),
                    (string) ($asset->category_code ?? __('Ohne Produktgruppe')),
                    ['category_code' => $asset->category_code],
                ],
                'model' => [
                    trim((string) $asset->manufacturer) . '|' . trim((string) $asset->model),
                    trim(sprintf('%s %s', (string) $asset->manufacturer, (string) $asset->model)) ?: (string) __('Ohne Modell'),
                    ['manufacturer' => $asset->manufacturer, 'model' => $asset->model],
                ],
                default => [
                    'a:' . $asset->id,
                    sprintf('%s — %s', (string) $asset->asset_no, (string) $asset->name),
                    ['asset_id' => (int) $asset->id],
                ],
            };
            if (! isset($groups[$key])) {
                $groups[$key] = ['label' => $label, 'assetIds' => [], 'drilldown' => $drilldown];
            }
            $groups[$key]['assetIds'][] = (int) $asset->id;
        }

        $rows = [];
        foreach ($groups as $key => $group) {
            $entryCount = 0;
            $openCount = 0;
            $escCount = 0;
            $defectCount = 0;
            $lastIncident = null;
            foreach ($group['assetIds'] as $aid) {
                $entryCount += count($entriesByAsset[$aid] ?? []);
                $openCount += $openByAsset[$aid] ?? 0;
                $escCount += $escByAsset[$aid] ?? 0;
                $defectCount += $defectByAsset[$aid]['defects'] ?? 0;
                $candidate = $defectByAsset[$aid]['last'] ?? null;
                if ($candidate !== null && ($lastIncident === null || $candidate > $lastIncident)) {
                    $lastIncident = $candidate;
                }
            }
            $defectRate = $entryCount > 0 ? round(($defectCount / $entryCount) * 100, 2) : 0.0;

            // Globale Filter in Drilldown übernehmen, gruppen-spezifische Filter
            // gewinnen (z. B. asset_id schlägt category_code).
            $drilldownFilter = array_filter(
                array_merge(
                    [
                        'customer_id' => $customerId,
                        'category_code' => $categoryCode,
                        'manufacturer' => $manufacturer,
                    ],
                    $group['drilldown'],
                ),
                static fn($v) => $v !== null && $v !== '',
            );

            $rows[] = [
                'key' => $key,
                'label' => $group['label'],
                'assetCount' => count($group['assetIds']),
                'entryCount' => $entryCount,
                'openIssueCount' => $openCount,
                'escalationCount' => $escCount,
                'defectCount' => $defectCount,
                'defectRate' => $defectRate,
                'lastIncidentAt' => $lastIncident,
                'drilldown' => $drilldownFilter,
            ];
        }

        usort($rows, static fn(array $a, array $b): int => $b['defectCount'] <=> $a['defectCount']
            ?: strnatcasecmp($a['label'], $b['label']));

        return $rows;
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
