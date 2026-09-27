<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SiteBenchmarkService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sustainability;

use App\Models\Sustainability\{SustainabilityActivityRecord, SustainabilitySite};
use App\Support\MorphMap;
use App\Support\Query\DateRange;

/**
 * Standort-Benchmarking (MVP-929): Emissionen je Standort und Jahr über die
 * bestehende Faktorberechnung, Intensitäten je m² und je beschäftigter Person.
 * Aktivitäten ohne Faktor zählen nicht mit und werden als Lücke ausgewiesen.
 */
final class SiteBenchmarkService {
    public function __construct(private readonly EmissionCalculationService $emissions) {}

    /**
     * @return list<array{site: SustainabilitySite, co2e_kg: float, by_scope: array<int, float>, per_m2: ?float, per_head: ?float, missing: int, records: int}>
     */
    public function benchmark(int $organizationId, int $year): array {
        $sites = SustainabilitySite::query()->where('organization_id', $organizationId)->orderBy('name')->get()->keyBy('id');
        $rows = [];
        foreach ($sites as $site) {
            $rows[$site->id] = ['site' => $site, 'co2e_kg' => 0.0, 'by_scope' => [], 'per_m2' => null, 'per_head' => null, 'missing' => 0, 'records' => 0];
        }
        $records = SustainabilityActivityRecord::query()
            ->where('organization_id', $organizationId)
            ->where('subject_type', MorphMap::alias(SustainabilitySite::class))
            ->whereIn('subject_id', $sites->keys())
            ->whereBetween('period_end', DateRange::days($year . '-01-01', $year . '-12-31'))
            ->get();
        foreach ($records as $record) {
            $id = (int) $record->subject_id;
            if (! isset($rows[$id])) {
                continue;
            }
            $rows[$id]['records']++;
            ['co2e_kg' => $co2e, 'factor' => $factor] = $this->emissions->co2eFor($record);
            if ($co2e === null || $factor === null) {
                $rows[$id]['missing']++;

                continue;
            }
            $rows[$id]['co2e_kg'] = round($rows[$id]['co2e_kg'] + $co2e, 3);
            $scope = (int) $factor->scope;
            $rows[$id]['by_scope'][$scope] = round(($rows[$id]['by_scope'][$scope] ?? 0.0) + $co2e, 3);
        }
        foreach ($rows as $id => $row) {
            $area = (float) ($row['site']->area_m2 ?? 0);
            $head = (int) ($row['site']->headcount ?? 0);
            $rows[$id]['per_m2'] = $area > 0 ? round($row['co2e_kg'] / $area, 2) : null;
            $rows[$id]['per_head'] = $head > 0 ? round($row['co2e_kg'] / $head, 1) : null;
        }
        $rows = array_values($rows);
        usort($rows, static fn (array $a, array $b): int => ($b['per_m2'] ?? -1) <=> ($a['per_m2'] ?? -1));

        return $rows;
    }
}
