<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceClassificationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AssetFinance;

use App\Models\AssetFinance\AssetFinanceContract;
use App\Models\Platform\User;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * IFRS-16-/HGB-Einschätzung (MVP-947) als vorbereitende Referenz ohne
 * Bilanzierungszusage. IFRS 16 aus Sicht des Leasingnehmers mit den
 * Ausnahmen kurzfristig und geringwertig; HGB/Steuer nach dem
 * Vollamortisationserlass (Grundmietzeit 40–90 % der Nutzungsdauer,
 * Kaufoption, Spezialleasing).
 */
final class AssetFinanceClassificationService {
    /** Richtwert der Geringwertigkeit (IFRS 16 B3–B8), keine Norm. */
    public const LOW_VALUE_THRESHOLD = '5000.00';

    /**
     * @return array{term_months: ?int, ratio: ?float, ifrs16: string, hgb: string, reasons: list<string>}
     */
    public function assess(AssetFinanceContract $contract): array {
        $term = $contract->ends_on !== null ? (int) round($contract->starts_on->diffInMonths($contract->ends_on)) : null;
        $reasons = [];

        $ifrs16 = match (true) {
            $term !== null && $term <= 12 && $contract->purchase_option_amount === null => 'short_term',
            $contract->asset_value_amount !== null && NumberHelper::comparePrecise((string) $contract->asset_value_amount, self::LOW_VALUE_THRESHOLD, 2) <= 0 => 'low_value',
            default => 'right_of_use',
        };

        $ratio = $term !== null && $contract->useful_life_months !== null && $contract->useful_life_months > 0
            ? round($term / $contract->useful_life_months, 4)
            : null;
        if ($contract->is_special_lease) {
            $hgb = 'lessee';
            $reasons[] = 'special_lease';
        } elseif ($ratio === null) {
            $hgb = 'unknown';
            $reasons[] = 'missing_inputs';
        } elseif ($ratio < 0.4 || $ratio > 0.9) {
            $hgb = 'lessee';
            $reasons[] = $ratio < 0.4 ? 'term_below_40' : 'term_above_90';
        } elseif ($contract->purchase_option_amount !== null && $contract->residual_value !== null
            && NumberHelper::comparePrecise((string) $contract->purchase_option_amount, (string) $contract->residual_value, 2) < 0) {
            $hgb = 'lessee';
            $reasons[] = 'bargain_option';
        } else {
            $hgb = 'lessor';
            $reasons[] = 'term_within';
        }

        return ['term_months' => $term, 'ratio' => $ratio, 'ifrs16' => $ifrs16, 'hgb' => $hgb, 'reasons' => $reasons];
    }

    /** @param array{useful_life_months?: ?int, asset_value_amount?: ?string, is_special_lease?: bool} $inputs */
    public function save(AssetFinanceContract $contract, array $inputs, User $actor): AssetFinanceContract {
        $contract->forceFill($inputs);
        $result = $this->assess($contract);
        $contract->forceFill(['classification_snapshot' => $result + ['assessed_at' => now()->toIso8601String(), 'assessed_by' => $actor->id]])->save();
        $contract->audit('assetFinance.classified', ['ifrs16' => $result['ifrs16'], 'hgb' => $result['hgb']]);

        return $contract;
    }
}
