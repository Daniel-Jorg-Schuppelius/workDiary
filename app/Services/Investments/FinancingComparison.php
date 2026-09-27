<?php
/*
 * Created on   : Sat Sep 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinancingComparison.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Investments;

use App\Enums\Investments\InvestmentFinancingKind;
use App\Models\Investments\{InvestmentFinancingVariant, InvestmentOption};
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Finanzierungsvergleich einer Investitionsoption (MVP-907): je Variante
 * Monatsrate, Gesamtkosten, Zinsanteil (Kredit) und Mehrkosten gegenüber dem
 * Kauf aus Eigenmitteln (einmalige Kosten der Option). Kredite rechnen als
 * Annuität mit optionaler Schlussrate, Leasing als Rate × Laufzeit plus
 * Sonderzahlung und Restwert; Gebühren kommen hinzu. Laufende Kosten der
 * Option fallen bei allen Varianten gleich an und bleiben außen vor.
 */
final class FinancingComparison {
    /**
     * @return list<array{variant: InvestmentFinancingVariant, monthly: ?string, total: string, interest: ?string, extra: string, schedule: list<array{period: int, payment: numeric-string, interest: numeric-string, principal: numeric-string, balance: numeric-string}>}>
     */
    public function evaluate(InvestmentOption $option): array {
        $price = self::amount($option->one_time_cost);
        $rows = [];
        foreach ($option->financingVariants as $variant) {
            $down = self::amount($variant->down_payment_amount);
            $residual = self::amount($variant->residual_amount);
            $term = max(1, (int) $variant->term_months);
            $monthly = null;
            $interest = null;
            $schedule = [];

            if ($variant->kind === InvestmentFinancingKind::Loan) {
                $schedule = NumberHelper::amortizationSchedule(bcsub($price, $down, 2), self::amount($variant->interest_rate, 3), $term, 12, $residual);
                $monthly = $schedule[0]['payment'];
                $interest = array_reduce($schedule, static fn (string $sum, array $row): string => bcadd($sum, $row['interest'], 2), '0.00');
                $paid = bcadd($price, $interest, 2);
            } elseif ($variant->kind === InvestmentFinancingKind::Lease) {
                $monthly = self::amount($variant->rate_amount);
                $paid = bcadd(bcadd($down, bcmul($monthly, (string) $term, 2), 2), $residual, 2);
            } else {
                $paid = $price;
            }
            $total = bcadd($paid, self::amount($variant->fee_amount), 2);

            $rows[] = ['variant' => $variant, 'monthly' => $monthly, 'total' => $total, 'interest' => $interest, 'extra' => bcsub($total, $price, 2), 'schedule' => $schedule];
        }

        return $rows;
    }

    /** @return numeric-string */
    private static function amount(mixed $value, int $scale = 2): string {
        return bcadd(is_numeric($value) ? (string) $value : '0', '0', $scale);
    }
}
