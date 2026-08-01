<?php
/*
 * Created on   : Mon Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetAnalysisReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\Protocol\ProtocolType;
use App\Models\{Asset, DiaryEntry, OpenIssue, Protocol};
use Carbon\CarbonImmutable;

/**
 * Produkt-/Objekt-/Raumanalyse (MVP-041): Aufträge, offene Punkte, Defekte
 * und Defektrate je Asset / Produktgruppe / Modell, inkl. Drilldown-Filter.
 *
 * Reine Datenaufbereitung, getrennt vom Controller (HTTP-Filter, CSV/PDF,
 * Audit), Muster wie {@see PlanIstReportBuilder}.
 */
class AssetAnalysisReportBuilder {
    /**
     * @param  list<int>  $excludedCustomerIds  Feature 002: Assets und Aufträge
     *         org-weit ausgeblendeter Kunden entfallen; Übersteuerung regelt der Aufrufer.
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
    public function build(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $customerId,
        ?string $categoryCode,
        ?string $manufacturer,
        string $groupBy,
        array $excludedCustomerIds = [],
    ): array {
        $assets = Asset::query()
            ->with('product:id,name')
            ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
            // NOT IN würde NULL-Kunden mit verwerfen — kundenlose Assets bleiben sichtbar.
            ->when($excludedCustomerIds !== [], fn($q) => $q->where(
                fn($w) => $w->whereNull('customer_id')->orWhereNotIn('customer_id', $excludedCustomerIds),
            ))
            ->when($categoryCode !== null, fn($q) => $q->where('category_code', $categoryCode))
            ->when($manufacturer !== null, fn($q) => $q->where('manufacturer', $manufacturer))
            ->get(['id', 'name', 'asset_no', 'category_code', 'manufacturer', 'model', 'product_id']);

        if ($assets->isEmpty()) {
            return [];
        }

        /** @var list<int> $assetIds */
        $assetIds = $assets->pluck('id')->map(static fn($v): int => (int) $v)->values()->all();

        $entryRows = DiaryEntry::query()
            ->whereIn('asset_id', $assetIds)
            ->whereBetween('created_at', [$from, $to])
            // Feature 002: Aufträge ausgeblendeter Kunden zählen nicht in die
            // Asset-Kennzahlen (kundenlose Aufträge bleiben sichtbar).
            ->when($excludedCustomerIds !== [], fn($q) => $q->where(
                fn($w) => $w->whereNull('customer_id')->orWhereNotIn('customer_id', $excludedCustomerIds),
            ))
            ->get(['id', 'asset_id']);

        /** @var array<int, list<int>> $entriesByAsset */
        $entriesByAsset = [];
        $allEntryIds = [];
        foreach ($entryRows as $entry) {
            $aid = (int) $entry->asset_id;
            $entriesByAsset[$aid][] = (int) $entry->id;
            $allEntryIds[] = (int) $entry->id;
        }

        $openStatuses = OpenIssueStatus::openValues();

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
                // MVP-371 (produktmodell-konzept.md): typisierte Assets gruppieren
                // über das Produkt (stabiler Schlüssel statt String-Paar);
                // untypisierte fallen auf manufacturer|model zurück.
                'model' => $asset->product_id !== null
                    ? [
                        'product:' . (int) $asset->product_id,
                        (string) ($asset->product->name ?? __('Ohne Modell')),
                        ['product_id' => (int) $asset->product_id],
                    ]
                    : [
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
}
