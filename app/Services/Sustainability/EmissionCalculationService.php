<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmissionCalculationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sustainability;

use App\Models\Sustainability\{SustainabilityActivityRecord, SustainabilityEmissionFactor, SustainabilityFactorSet};

/**
 * CO2e-Berechnung (Feature 071, MVP-227/228): löst Emissionsfaktoren
 * STICHTAGSBEZOGEN auf (Periodenende) — Org-Sets überschreiben die
 * ausgelieferten Standard-Sets (Blueprint PerDiemRate/P1). Jede Zahl
 * bleibt bis zu Faktorquelle, Jahr, Gültigkeit und Datenqualität
 * erklärbar (Greenwashing-Schutz).
 */
class EmissionCalculationService {
    /**
     * Faktor zum Stichtag: Org-Set vor globalem Set; innerhalb des Sets
     * der jüngste zum Stichtag gültige Faktor.
     */
    public function resolveFactor(int $organizationId, string $activityCode, \DateTimeInterface $onDate): ?SustainabilityEmissionFactor {
        foreach ([$organizationId, null] as $owner) {
            $factor = SustainabilityEmissionFactor::query()
                ->whereHas('set', function ($q) use ($owner): void {
                    $q->where('active', true);
                    $owner === null ? $q->whereNull('organization_id') : $q->where('organization_id', $owner);
                })
                ->where('activity_code', $activityCode)
                ->whereDate('valid_from', '<=', $onDate)
                ->where(fn($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $onDate))
                ->orderByDesc('valid_from')
                ->first();
            if ($factor !== null) {
                return $factor;
            }
        }

        return null;
    }

    /**
     * @return array{co2e_kg: float|null, factor: SustainabilityEmissionFactor|null}
     */
    public function co2eFor(SustainabilityActivityRecord $record): array {
        $factor = $this->resolveFactor((int) $record->organization_id, $record->activity_code, $record->period_end);
        if ($factor === null) {
            return ['co2e_kg' => null, 'factor' => null];
        }

        return [
            'co2e_kg' => round((float) $record->amount * (float) $factor->factor, 3),
            'factor' => $factor,
        ];
    }

    /**
     * Aggregation je Zeitraum: CO2e nach Scope, Aktivitätssummen,
     * Datenqualitätsanteile und fehlende Faktoren (Warnung statt stiller 0).
     *
     * @return array{
     *   co2e_total_kg: float,
     *   co2e_by_scope: array<int, float>,
     *   activities: array<string, array{amount: float, unit: string, co2e_kg: float|null, factor_source: string|null, quality: string|null}>,
     *   quality_share: array<string, int>,
     *   missing_factors: list<string>
     * }
     */
    public function aggregate(int $organizationId, string $from, string $to): array {
        $records = SustainabilityActivityRecord::query()
            ->where('organization_id', $organizationId)
            ->whereDate('period_end', '>=', $from)
            ->whereDate('period_end', '<=', $to)
            ->get();

        $total = 0.0;
        $byScope = [];
        $activities = [];
        $quality = [];
        $missing = [];

        foreach ($records as $record) {
            $quality[$record->data_quality] = ($quality[$record->data_quality] ?? 0) + 1;
            $code = (string) $record->activity_code;
            $activities[$code] ??= ['amount' => 0.0, 'unit' => (string) $record->unit, 'co2e_kg' => 0.0, 'factor_source' => null, 'quality' => null];
            $activities[$code]['amount'] += (float) $record->amount;

            ['co2e_kg' => $co2e, 'factor' => $factor] = $this->co2eFor($record);
            if ($co2e === null || $factor === null) {
                $activities[$code]['co2e_kg'] = null;
                if (! in_array($code, $missing, true)) {
                    $missing[] = $code;
                }

                continue;
            }
            if ($activities[$code]['co2e_kg'] !== null) {
                $activities[$code]['co2e_kg'] = round($activities[$code]['co2e_kg'] + $co2e, 3);
            }
            $set = $factor->set;
            $activities[$code]['factor_source'] = $set !== null ? $set->name . ' ' . $set->year : null;
            $activities[$code]['quality'] = $factor->quality;
            $total += $co2e;
            $byScope[(int) $factor->scope] = round(($byScope[(int) $factor->scope] ?? 0.0) + $co2e, 3);
        }

        return [
            'co2e_total_kg' => round($total, 3),
            'co2e_by_scope' => $byScope,
            'activities' => $activities,
            'quality_share' => $quality,
            'missing_factors' => $missing,
        ];
    }

    /**
     * Aktive Faktor-Setnamen (Methodik-Angabe für Exporte/Snapshots).
     *
     * @return array<int, string>
     */
    public function activeSetNames(int $organizationId): array {
        return SustainabilityFactorSet::query()
            ->where('active', true)
            ->where(fn($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organizationId))
            ->get()
            ->map(fn(SustainabilityFactorSet $set): string => $set->name . ' ' . $set->year . ' (' . ($set->source ?? '—') . ')')
            ->values()
            ->all();
    }
}
