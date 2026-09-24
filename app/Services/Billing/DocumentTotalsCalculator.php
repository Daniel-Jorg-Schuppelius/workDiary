<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentTotalsCalculator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Contracts\DocumentLine;
use App\Services\Billing\Dto\DocumentTotalsContext;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\{Money, Percentage};

/**
 * Die eine Rechenstelle für Belegpositionen und Belegsummen (MVP-416,
 * MVP-865; vorher `Invoicing\InvoiceTotalsCalculator`).
 *
 * Regeln: Zeilennetto = Einzelpreis × Menge (Rundung auf der Preisskala),
 * abzüglich Positionsrabatt — Prozent hat Vorrang vor Betrag. Belegsummen:
 * Zeilennettos je Steuersatz gruppiert, Belegrabatt anteilig je Satz
 * (größter Rest), Steuer auf die rabattierte Basis und PRO SATZ gerundet
 * (§ 14 UStG-üblich), Reverse Charge ohne Steuer. Ein Belegrabatt-Betrag
 * kappt bei der Positionssumme und folgt deren Vorzeichen (Gutschrift).
 *
 * @phpstan-type RateGroup array{rate: float, net: Money, allowance: Money, taxable: Money, tax: Money}
 * @phpstan-type Totals array{line_net_sum: Money, document_discount: Money, by_rate: array<string, RateGroup>, subtotal: Money, tax_amount: Money, total: Money}
 */
class DocumentTotalsCalculator {
    public static function lineNet(
        string|int|float $quantity,
        Money|string|float|int|null $unitPrice,
        Percentage|string|float|int|null $discountPercent = null,
        Money|string|float|int|null $discountAmount = null,
        CurrencyCode $currency = CurrencyCode::Euro,
    ): Money {
        $base = self::money($unitPrice, $currency)->times(self::factor($quantity));
        $percent = self::percent($discountPercent);
        if ($percent !== null && $percent->isPositive()) {
            return $percent->subtractFrom($base);
        }
        $discount = self::moneyOrNull($discountAmount, $base->getCurrency());
        if ($discount !== null && ! $discount->isZero()) {
            return $base->minus($discount);
        }

        return $base;
    }

    /**
     * @param  iterable<DocumentLine>  $lines
     * @return Totals
     */
    public function totals(iterable $lines, DocumentTotalsContext $context): array {
        $currency = $context->currency;
        $scale = $currency->getDefaultFractionDigits();
        $zero = Money::zero($currency);

        /** @var array<string, Money> $byRate */
        $byRate = [];
        foreach ($lines as $line) {
            $rate = $line->taxRate() ?? $context->fallbackTaxRate;
            $key = self::rateKey($rate);
            // Zeilenbeträge gehen in Währungspräzision in die Summe — auch
            // Bieterangaben mit 4 NK (LV); die Rechnung speichert ohnehin Cent.
            $net = $line->netAmount()->withScale($scale);
            $byRate[$key] = isset($byRate[$key]) ? $byRate[$key]->plus($net) : $net;
        }
        $lineNetSum = Money::sum(array_values($byRate), $currency);
        ksort($byRate);

        $documentDiscount = $this->documentDiscount($context, $lineNetSum);
        $allocation = $this->allocate($documentDiscount, $byRate, $lineNetSum);

        $result = [];
        $subtotal = $zero;
        $tax = $zero;
        foreach ($byRate as $key => $net) {
            $allowance = $allocation[$key] ?? $zero;
            $taxable = $net->minus($allowance);
            $rateTax = $context->reverseCharge ? $zero : $taxable->percentage((float) $key);
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

    /** Belegrabatt: Prozent vom Zeilennetto, sonst Betrag gekappt auf die Positionssumme (vorzeichentreu). */
    public function documentDiscount(DocumentTotalsContext $context, Money $lineNetSum): Money {
        $currency = $lineNetSum->getCurrency();
        $zero = Money::zero($currency);
        $percent = $context->discountPercent;
        if ($percent !== null && $percent->isPositive()) {
            return $percent->amountOf($lineNetSum);
        }
        $amount = $context->discountAmount;
        if ($amount === null || $amount->isZero() || $lineNetSum->isZero()) {
            return $zero;
        }
        if ($lineNetSum->isPositive()) {
            return $amount->isPositive() ? Money::min($amount, $lineNetSum) : $zero;
        }

        return $amount->isNegative() ? Money::max($amount, $lineNetSum) : $zero;
    }

    /** Steuersatz-Schlüssel im US-Format mit zwei Nachkommastellen („19.00"). */
    public static function rateKey(?Percentage $rate): string {
        return NumberHelper::toUSFormat($rate !== null ? (float) $rate->getNumericValue() : 0.0, 2);
    }

    /**
     * Betrag aus Money, Dezimalstring oder Zahl. Strings behalten ihre
     * Nachkommastellen (Einzelpreise mit 4 NK), mindestens die der Währung.
     */
    public static function money(Money|string|float|int|null $value, CurrencyCode $currency): Money {
        return self::moneyOrNull($value, $currency) ?? Money::zero($currency);
    }

    public static function moneyOrNull(Money|string|float|int|null $value, CurrencyCode $currency): ?Money {
        if ($value instanceof Money) {
            return $value;
        }
        if ($value === null || $value === '') {
            return null;
        }
        $amount = NumberHelper::normalizeDecimalString((string) $value);
        $decimals = ($dot = strrpos($amount, '.')) === false ? 0 : strlen($amount) - $dot - 1;

        return Money::of($amount, $currency, max($currency->getDefaultFractionDigits(), $decimals));
    }

    public static function percent(Percentage|string|float|int|null $value): ?Percentage {
        if ($value instanceof Percentage) {
            return $value;
        }
        if ($value === null || $value === '') {
            return null;
        }

        return Percentage::tryFrom(NumberHelper::normalizeDecimalString((string) $value));
    }

    /**
     * @param  array<string, Money>  $byRate
     * @return array<string, Money>
     */
    private function allocate(Money $discount, array $byRate, Money $lineNetSum): array {
        if ($discount->isZero() || $lineNetSum->isZero() || $byRate === []) {
            return [];
        }

        return $discount->allocateByWeights(
            array_map(static fn (Money $net): string => $net->getAmount(), $byRate)
        );
    }

    /** @return float|int|numeric-string */
    private static function factor(string|int|float $quantity): string|int|float {
        return is_string($quantity) ? NumberHelper::normalizeDecimalString($quantity) : $quantity;
    }
}
