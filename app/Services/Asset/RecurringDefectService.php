<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurringDefectService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Asset;

use App\Models\{Asset, AssetDefect};
use App\Models\Scopes\OrganizationScope;
use Carbon\{CarbonImmutable, CarbonInterface};

/**
 * Wiederholdefekt-Statistik (Feature 009 → Rang 47).
 *
 * Ein Asset gilt als „Wiederholdefekt-Fall", wenn es innerhalb der letzten
 * {@see WINDOW_MONTHS} Monate mindestens {@see THRESHOLD} Defekte gemeldet hat.
 * Die Auswertung liefert eine Pareto-Sicht (Top-Verursacher nach Defektzahl im
 * gewählten Zeitraum) samt Schweregrad-Aufschlüsselung und diesem Flag.
 *
 * Hinweis: `asset_defects` trägt keine eigene Defekt-Typ-Klassifikation
 * (nur `severity`/`title`); gruppiert wird daher nach Asset (+ severity), nicht
 * nach Defekt-Typ.
 */
class RecurringDefectService {
    /** Schwelle „Wiederholdefekt": Defekte im Fenster. */
    public const THRESHOLD = 3;

    /** Betrachtungsfenster in Monaten. */
    public const WINDOW_MONTHS = 12;

    /**
     * Pareto der Assets nach Defektzahl im Zeitraum [$from, $to], absteigend.
     * `is_recurring` bezieht sich auf die feste Schwelle (≥ THRESHOLD Defekte im
     * 12-Monats-Fenster bis $to), unabhängig von der gewählten Zeitraumlänge.
     *
     * @return list<array{asset_id: int, asset_sqid: string|null, asset_name: string, asset_no: string|null, total: int, by_severity: array<string, int>, recent_total: int, is_recurring: bool}>
     */
    public function pareto(int $organizationId, CarbonInterface $from, CarbonInterface $to): array {
        $periodRows = AssetDefect::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->whereBetween('reported_at', [$from, $to])
            ->get(['asset_id', 'severity']);

        if ($periodRows->isEmpty()) {
            return [];
        }

        /** @var array<int, array{total: int, by_severity: array<string, int>}> $perAsset */
        $perAsset = [];
        foreach ($periodRows as $defect) {
            $assetId = (int) $defect->asset_id;
            if (! isset($perAsset[$assetId])) {
                $perAsset[$assetId] = ['total' => 0, 'by_severity' => []];
            }
            $perAsset[$assetId]['total']++;
            $sev = $defect->severity->value;
            $perAsset[$assetId]['by_severity'][$sev] = ($perAsset[$assetId]['by_severity'][$sev] ?? 0) + 1;
        }

        $assetIds = array_keys($perAsset);

        // 12-Monats-Fenster bis $to → Wiederholdefekt-Flag (je Asset).
        $windowFrom = CarbonImmutable::instance($to)->subMonths(self::WINDOW_MONTHS);
        /** @var array<int, int> $recent */
        $recent = AssetDefect::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->whereIn('asset_id', $assetIds)
            ->whereBetween('reported_at', [$windowFrom, $to])
            ->selectRaw('asset_id, COUNT(*) as aggregate')
            ->groupBy('asset_id')
            ->pluck('aggregate', 'asset_id')
            ->map(static fn ($v): int => (int) $v)
            ->all();

        /** @var array<int, Asset> $assets */
        $assets = Asset::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->whereIn('id', $assetIds)
            ->get(['id', 'name', 'asset_no'])
            ->keyBy('id')
            ->all();

        $out = [];
        foreach ($perAsset as $assetId => $data) {
            $asset = $assets[$assetId] ?? null;
            $recentTotal = $recent[$assetId] ?? 0;
            $out[] = [
                'asset_id' => $assetId,
                'asset_sqid' => $asset !== null ? (string) $asset->getRouteKey() : null,
                'asset_name' => $asset !== null ? (string) $asset->name : '#' . $assetId,
                'asset_no' => $asset?->asset_no,
                'total' => $data['total'],
                'by_severity' => $data['by_severity'],
                'recent_total' => $recentTotal,
                'is_recurring' => $recentTotal >= self::THRESHOLD,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['asset_name'], $b['asset_name']));

        return $out;
    }

    /**
     * Ist das Asset ein Wiederholdefekt-Fall? (≥ THRESHOLD Defekte im
     * 12-Monats-Fenster bis $reference — Default: jetzt.)
     */
    public function isRecurring(Asset $asset, ?CarbonInterface $reference = null): bool {
        $reference ??= CarbonImmutable::now();
        $windowFrom = CarbonImmutable::instance($reference)->subMonths(self::WINDOW_MONTHS);

        $count = AssetDefect::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $asset->organization_id)
            ->where('asset_id', $asset->id)
            ->whereBetween('reported_at', [$windowFrom, $reference])
            ->count();

        return $count >= self::THRESHOLD;
    }
}
