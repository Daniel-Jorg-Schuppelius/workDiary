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
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;

/**
 * Zentrale Summenlogik (MVP-416): Positionsrabatt → Zeilennetto,
 * Belegrabatt → anteilige Zuordnung je Steuersatz (größter-Rest-Verfahren),
 * Steuer PRO SATZ gerundet. Einzige Rechenstelle für Invoice::recalculate(),
 * E-Rechnungs-Preflight und XML-Aufbau — sonst laufen die Sichten auseinander.
 *
 * Gerechnet wird durchgängig mit {@see Money} (bcmath): Positionssumme,
 * Rabattverteilung und Steuer gehen exakt auf, ohne float-Restcents.
 */
class InvoiceTotalsCalculator {
    /** Zeilennetto: Menge × Preis, dann Rabatt (Prozent XOR Betrag). */
    public static function lineNet(float $quantity, Money|string|float $unitPrice, ?float $discountPercent, Money|string|float|null $discountAmount, CurrencyCode $currency = CurrencyCode::Euro): Money {
        $base = self::money($unitPrice, $currency)->times($quantity);

        if ($discountPercent !== null && $discountPercent > 0) {
            return $base->minusPercentage($discountPercent);
        }

        $discount = $discountAmount !== null ? self::money($discountAmount, $currency) : null;
        if ($discount !== null && !$discount->isZero()) {
            return $base->minus($discount);
        }

        return $base;
    }

    /**
     * Vollständige Summen einer Rechnung aus geladenen Positionen.
     *
     * @return array{
     *   line_net_sum: Money,
     *   document_discount: Money,
     *   by_rate: array<int|string, array{rate: float, net: Money, allowance: Money, taxable: Money, tax: Money}>,
     *   subtotal: Money,
     *   tax_amount: Money,
     *   total: Money,
     * }
     */
    public function compute(Invoice $invoice): array {
        $currency = $invoice->currency ?? CurrencyCode::Euro;
        $zero = Money::zero($currency);

        $byRate = [];
        foreach ($invoice->items as $item) {
            $rate = $item->tax_rate !== null ? (float) $item->tax_rate : (float) $invoice->tax_rate;
            $key = number_format($rate, 2, '.', '');
            $net = self::money($item->amount, $currency);
            $byRate[$key] = isset($byRate[$key]) ? $byRate[$key]->plus($net) : $net;
        }
        $lineNetSum = Money::sum(array_values($byRate), $currency);
        ksort($byRate);

        $documentDiscount = $this->documentDiscount($invoice, $lineNetSum);
        $allocation = $this->allocate($documentDiscount, $byRate, $lineNetSum);

        $result = [];
        $subtotal = $zero;
        $tax = $zero;
        foreach ($byRate as $key => $net) {
            $allowance = $allocation[$key] ?? $zero;
            $taxable = $net->minus($allowance);
            $rateTax = $invoice->is_reverse_charge ? $zero : $taxable->percentage((float) $key);
            $result[$key] = [
                'rate' => (float) $key,
                'net' => $net,
                'allowance' => $allowance,
                'taxable' => $taxable,
                'tax' => $rateTax,
            ];
            $subtotal = $subtotal->plus($taxable);
            $tax = $tax->plus($rateTax);
        }

        return [
            'line_net_sum' => $lineNetSum,
            'document_discount' => $documentDiscount,
            'by_rate' => $result,
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total' => $subtotal->plus($tax),
        ];
    }

    /**
     * Belegrabatt in Belegwährung (Prozent XOR Betrag), betraglich nie mehr als
     * die Positionssumme. Vorzeichenfest: Storno/Gutschrift spiegeln negative
     * Positionssummen — der Prozentrabatt wird dann ebenfalls negativ, ein
     * fester Betrag wird beim Klonen negiert (MVP-416).
     */
    public function documentDiscount(Invoice $invoice, Money $lineNetSum): Money {
        $currency = $lineNetSum->getCurrency();
        $zero = Money::zero($currency);

        $percent = $invoice->discount_percent !== null ? (float) $invoice->discount_percent : null;
        if ($percent !== null && $percent > 0) {
            return $lineNetSum->percentage($percent);
        }

        $amount = $invoice->discount_amount !== null ? self::money($invoice->discount_amount, $currency) : null;
        if ($amount === null || $amount->isZero() || $lineNetSum->isZero()) {
            return $zero;
        }

        if ($lineNetSum->isPositive()) {
            return $amount->isPositive() ? Money::min($amount, $lineNetSum) : $zero;
        }

        return $amount->isNegative() ? Money::max($amount, $lineNetSum) : $zero;
    }

    /**
     * Belegrabatt anteilig je Steuersatz verteilen (größter Rest, Summe exakt).
     * Vorzeichenfest für Storno/Gutschrift (negative Summen) — {@see Money::allocateByWeights()}
     * garantiert, dass die Teilbeträge exakt den Belegrabatt ergeben.
     *
     * @param  array<int|string, Money>  $byRate
     * @return array<int|string, Money>
     */
    private function allocate(Money $discount, array $byRate, Money $lineNetSum): array {
        if ($discount->isZero() || $lineNetSum->isZero() || $byRate === []) {
            return [];
        }

        return $discount->allocateByWeights(
            array_map(static fn (Money $net): string => $net->getAmount(), $byRate)
        );
    }

    /**
     * Rohwert (Spaltenstring, Money) auf Money in der Belegwährung.
     */
    private static function money(Money|string|float|int|null $value, CurrencyCode $currency): Money {
        if ($value instanceof Money) {
            return $value;
        }

        return $value === null ? Money::zero($currency) : Money::of((string) $value, $currency);
    }
}
