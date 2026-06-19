<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SpcService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Enums\Procedure\ProcedureStepType;
use App\Models\{ProcedureStepDef, ProcedureStepRun};

/**
 * Statistische Prozesslenkung (SPC) für Mess-Schritte (Feature 047/048, E7).
 * Aggregiert die in den Schrittausführungen (`value_json.values`) erfassten
 * Messwerte eines Messreihen-Schritts zu Kennzahlen und – sofern Spezifikations-
 * grenzen am Schritt (`config.lsl`/`config.usl`) hinterlegt sind – zu den
 * Prozessfähigkeitsindizes Cp/Cpk sowie der Zahl der Werte außerhalb der Toleranz.
 *
 * Konvention: Step-Def `config` = {lsl, usl, nominal, unit}; Step-Run
 * `value_json` = {values: [zahl, …]} oder {value: zahl}.
 *
 * @phpstan-type SpcResult array{count: int, mean: float, min: float, max: float, std_dev: float, cp: float|null, cpk: float|null, out_of_spec: int}
 */
class SpcService {
    /** @return SpcResult|null null, wenn kein Mess-Schritt oder keine Werte */
    public function analyzeStep(ProcedureStepDef $def): ?array {
        if ($def->step_type !== ProcedureStepType::Messreihe) {
            return null;
        }

        $values = [];
        $runs = ProcedureStepRun::query()
            ->where('procedure_step_def_id', $def->id)
            ->whereNotNull('value_json')
            ->get();
        foreach ($runs as $run) {
            foreach ($this->extractValues($run->value_json) as $value) {
                $values[] = $value;
            }
        }

        if ($values === []) {
            return null;
        }

        return $this->stats($values, is_array($def->config) ? $def->config : []);
    }

    /**
     * @param  array<string, mixed>|null  $valueJson
     * @return list<float>
     */
    private function extractValues(?array $valueJson): array {
        if ($valueJson === null) {
            return [];
        }
        if (isset($valueJson['values']) && is_array($valueJson['values'])) {
            $out = [];
            foreach ($valueJson['values'] as $v) {
                if (is_numeric($v)) {
                    $out[] = (float) $v;
                }
            }

            return $out;
        }
        if (isset($valueJson['value']) && is_numeric($valueJson['value'])) {
            return [(float) $valueJson['value']];
        }

        return [];
    }

    /**
     * @param  non-empty-list<float>  $values
     * @param  array<string, mixed>  $config
     * @return SpcResult
     */
    private function stats(array $values, array $config): array {
        $count = count($values);
        $mean = array_sum($values) / $count;

        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }
        $variance /= $count;
        $stdDev = sqrt($variance);

        $lsl = isset($config['lsl']) && is_numeric($config['lsl']) ? (float) $config['lsl'] : null;
        $usl = isset($config['usl']) && is_numeric($config['usl']) ? (float) $config['usl'] : null;

        $cp = null;
        $cpk = null;
        if ($lsl !== null && $usl !== null && $stdDev > 0.0) {
            $cp = round(($usl - $lsl) / (6 * $stdDev), 4);
            $cpk = round(min($usl - $mean, $mean - $lsl) / (3 * $stdDev), 4);
        }

        $outOfSpec = 0;
        if ($lsl !== null || $usl !== null) {
            foreach ($values as $value) {
                if (($lsl !== null && $value < $lsl) || ($usl !== null && $value > $usl)) {
                    $outOfSpec++;
                }
            }
        }

        return [
            'count' => $count,
            'mean' => round($mean, 4),
            'min' => min($values),
            'max' => max($values),
            'std_dev' => round($stdDev, 4),
            'cp' => $cp,
            'cpk' => $cpk,
            'out_of_spec' => $outOfSpec,
        ];
    }
}
