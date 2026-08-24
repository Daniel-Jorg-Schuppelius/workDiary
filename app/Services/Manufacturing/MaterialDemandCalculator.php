<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialDemandCalculator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\Manufacturing\QuantityKind;
use App\Models\ProcedureMaterialRequirement;
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Collection;

/**
 * Deterministische, serverseitige Materialbedarfsberechnung (Feature 047).
 * Rein dezimal (bcmath), nie Fließkomma.
 *
 * - PerUnit: Menge × Sollmenge
 * - Fixed:   feste Menge (unabhängig von der Sollmenge)
 * - Ratio:   Sollmenge × Anteil / Summe der Anteile (Rezeptur, z. B. 1:3)
 *
 * Optionaler Verschnittzuschlag (Prozent) und Rundung (none/up/down) werden
 * danach angewandt.
 */
class MaterialDemandCalculator {
    public const SCALE = 4;

    private const WORK = 8;

    /**
     * @param  Collection<int, ProcedureMaterialRequirement>  $bom
     * @return list<array{requirement: ProcedureMaterialRequirement, demand: numeric-string}>
     */
    public function calculate(Collection $bom, string $targetQty): array {
        $target = NumberHelper::normalizeDecimalString($targetQty);

        $ratioSum = '0';
        foreach ($bom as $req) {
            if ($req->quantity_kind === QuantityKind::Ratio && $req->ratio_part !== null) {
                $ratioSum = bcadd($ratioSum, $req->ratio_part, self::WORK);
            }
        }

        $result = [];
        foreach ($bom as $req) {
            if (! $req->active) {
                continue;
            }

            $base = match ($req->quantity_kind) {
                QuantityKind::PerUnit => bcmul($req->quantity?->getNumericValue() ?? '0', $target, self::WORK),
                QuantityKind::Fixed => NumberHelper::normalizeDecimalString($req->quantity?->getNumericValue() ?? '0'),
                QuantityKind::Ratio => bccomp($ratioSum, '0', self::WORK) > 0 && $req->ratio_part !== null
                    ? bcmul($target, bcdiv($req->ratio_part, $ratioSum, self::WORK), self::WORK)
                    : '0',
            };

            if ($req->waste_surcharge !== null) {
                $factor = bcadd('1', bcdiv($req->waste_surcharge, '100', self::WORK), self::WORK);
                $base = bcmul($base, $factor, self::WORK);
            }

            $result[] = [
                'requirement' => $req,
                'demand' => $this->round($base, $req->rounding),
            ];
        }

        return $result;
    }

    /**
     * @param  numeric-string  $value
     * @return numeric-string
     */
    private function round(string $value, ?RoundingMode $rounding): string {
        // Ohne Modus (null): auf SCALE abschneiden. Mit Modus: auf ganze Einheiten
        // runden (Mengen sind nicht-negativ) und wieder auf SCALE formatieren.
        return $rounding === null
            ? bcadd($value, '0', self::SCALE)
            : bcadd(NumberHelper::roundPrecise($value, 0, $rounding), '0', self::SCALE);
    }

}
