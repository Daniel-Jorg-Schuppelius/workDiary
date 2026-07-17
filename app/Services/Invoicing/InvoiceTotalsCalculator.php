<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceTotalsCalculator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Invoicing;

use App\Models\Invoice;

/**
 * Zentrale Summenlogik (MVP-416): Positionsrabatt → Zeilennetto,
 * Belegrabatt → anteilige Zuordnung je Steuersatz (größter-Rest-Verfahren),
 * Steuer PRO SATZ gerundet. Einzige Rechenstelle für Invoice::recalculate(),
 * E-Rechnungs-Preflight und XML-Aufbau — sonst laufen die Sichten auseinander.
 */
class InvoiceTotalsCalculator {
    /** Zeilennetto: round(Menge × Preis, 2), dann Rabatt (Prozent XOR Betrag). */
    public static function lineNet(float $quantity, float $unitPrice, ?float $discountPercent, ?float $discountAmount): float {
        $base = round($quantity * $unitPrice, 2);
        if ($discountPercent !== null && $discountPercent > 0) {
            return round($base * (1 - $discountPercent / 100), 2);
        }
        if ($discountAmount !== null && $discountAmount != 0.0) {
            return round($base - $discountAmount, 2);
        }

        return $base;
    }

    /**
     * Vollständige Summen einer Rechnung aus geladenen Positionen.
     *
     * @return array{
     *   line_net_sum: float,
     *   document_discount: float,
     *   by_rate: array<int|string, array{rate: float, net: float, allowance: float, taxable: float, tax: float}>,
     *   subtotal: float,
     *   tax_amount: float,
     *   total: float,
     * }
     */
    public function compute(Invoice $invoice): array {
        $byRate = [];
        $lineNetSum = 0.0;
        foreach ($invoice->items as $item) {
            $rate = $item->tax_rate !== null ? (float) $item->tax_rate : (float) $invoice->tax_rate;
            $key = number_format($rate, 2, '.', '');
            $net = (float) $item->amount;
            $byRate[$key] = ($byRate[$key] ?? 0.0) + $net;
            $lineNetSum += $net;
        }
        $lineNetSum = round($lineNetSum, 2);
        ksort($byRate);

        $documentDiscount = $this->documentDiscount($invoice, $lineNetSum);
        $allocation = $this->allocate($documentDiscount, $byRate, $lineNetSum);

        $result = [];
        $subtotal = 0.0;
        $tax = 0.0;
        foreach ($byRate as $key => $net) {
            $net = round($net, 2);
            $allowance = $allocation[$key] ?? 0.0;
            $taxable = round($net - $allowance, 2);
            $rateTax = $invoice->is_reverse_charge ? 0.0 : round($taxable * ((float) $key) / 100, 2);
            $result[$key] = [
                'rate' => (float) $key,
                'net' => $net,
                'allowance' => $allowance,
                'taxable' => $taxable,
                'tax' => $rateTax,
            ];
            $subtotal += $taxable;
            $tax += $rateTax;
        }

        $subtotal = round($subtotal, 2);
        $tax = round($tax, 2);

        return [
            'line_net_sum' => $lineNetSum,
            'document_discount' => $documentDiscount,
            'by_rate' => $result,
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total' => round($subtotal + $tax, 2),
        ];
    }

    /**
     * Belegrabatt in EUR (Prozent XOR Betrag), betraglich nie mehr als die
     * Positionssumme. Vorzeichenfest: Storno/Gutschrift spiegeln negative
     * Positionssummen — der Prozentrabatt wird dann ebenfalls negativ, ein
     * fester Betrag wird beim Klonen negiert (MVP-416).
     */
    public function documentDiscount(Invoice $invoice, float $lineNetSum): float {
        $percent = $invoice->discount_percent !== null ? (float) $invoice->discount_percent : null;
        $amount = $invoice->discount_amount !== null ? (float) $invoice->discount_amount : null;

        if ($percent !== null && $percent > 0) {
            return round($lineNetSum * $percent / 100, 2);
        }
        if ($amount !== null && $amount != 0.0 && $lineNetSum != 0.0) {
            $amount = round($amount, 2);
            if ($lineNetSum > 0) {
                return $amount > 0 ? min($amount, $lineNetSum) : 0.0;
            }

            return $amount < 0 ? max($amount, $lineNetSum) : 0.0;
        }

        return 0.0;
    }

    /**
     * Belegrabatt anteilig je Steuersatz verteilen (größter Rest, Summe
     * exakt). Vorzeichenfest für Storno/Gutschrift (negative Summen).
     *
     * @param  array<int|string, float>  $byRate
     * @return array<int|string, float>
     */
    private function allocate(float $discount, array $byRate, float $lineNetSum): array {
        if ($discount == 0.0 || $lineNetSum == 0.0 || $byRate === []) {
            return [];
        }

        $allocation = [];
        $remainders = [];
        $allocated = 0.0;
        foreach ($byRate as $key => $net) {
            $exact = $discount * $net / $lineNetSum;
            // Richtung Null abschneiden; die Rest-Cents verteilt der größte Rest.
            $truncated = ($exact >= 0 ? floor($exact * 100) : ceil($exact * 100)) / 100;
            $allocation[$key] = $truncated;
            $remainders[$key] = abs($exact - $truncated);
            $allocated += $truncated;
        }

        $missing = (int) round(abs($discount - $allocated) * 100);
        $step = $discount >= 0 ? 0.01 : -0.01;
        arsort($remainders);
        foreach (array_keys($remainders) as $key) {
            if ($missing <= 0) {
                break;
            }
            $allocation[$key] = round($allocation[$key] + $step, 2);
            $missing--;
        }

        return $allocation;
    }
}
