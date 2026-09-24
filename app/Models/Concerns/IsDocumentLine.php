<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsDocumentLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Contracts\HasDocumentLines;
use App\Services\Billing\DocumentTotalsCalculator;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\{Money, Percentage, Quantity};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baustein für {@see \App\Models\Contracts\DocumentLine} (MVP-865): liest die
 * Vertragsfelder über die vorhandenen Spalten. Abweichende Spaltennamen nennt
 * das Modell in {@see lineColumns()} (`'tax_rate' => 'vat_rate'`,
 * `'net_amount' => 'total_price'`, `'position' => null` ohne Spalte).
 *
 * @mixin Model
 */
trait IsDocumentLine {
    /**
     * Vertragsfeld → Spaltenname; null = Feld gibt es an diesem Modell nicht.
     * Vertragsfelder: position, quantity, unit, unit_price, discount_percent,
     * discount_amount, tax_rate, net_amount, currency.
     *
     * @return array<string, string|null>
     */
    protected static function lineColumns(): array {
        return [];
    }

    public static function lineColumn(string $field): ?string {
        $map = static::lineColumns();

        return array_key_exists($field, $map) ? $map[$field] : $field;
    }

    abstract public function lineDocument(): BelongsTo;

    public function linePosition(): int {
        return (int) ($this->lineValue('position') ?? 0);
    }

    /** @return numeric-string */
    public function lineQuantity(): string {
        $value = $this->lineValue('quantity');
        return match (true) {
            $value instanceof Quantity => $value->getNumericValue(),
            $value === null, $value === '' => '0',
            default => NumberHelper::normalizeDecimalString((string) $value),
        };
    }

    public function lineUnit(): ?string {
        $value = $this->lineValue('unit');
        $unit = $value === null ? '' : trim((string) $value);

        return $unit === '' ? null : $unit;
    }

    public function lineCurrency(): CurrencyCode {
        $own = $this->lineValue('currency');
        if ($own instanceof CurrencyCode) {
            return $own;
        }
        if (is_string($own) && ($resolved = CurrencyCode::tryFrom(strtoupper($own))) !== null) {
            return $resolved;
        }
        $document = $this->lineDocumentModel();

        return $document instanceof HasDocumentLines ? $document->documentCurrency() : CurrencyCode::Euro;
    }

    public function unitPrice(): ?Money {
        return DocumentTotalsCalculator::moneyOrNull($this->lineValue('unit_price'), $this->lineCurrency());
    }

    public function discountPercent(): ?Percentage {
        return DocumentTotalsCalculator::percent($this->lineValue('discount_percent'));
    }

    public function discountAmount(): ?Money {
        return DocumentTotalsCalculator::moneyOrNull($this->lineValue('discount_amount'), $this->lineCurrency());
    }

    public function taxRate(): ?Percentage {
        return DocumentTotalsCalculator::percent($this->lineValue('tax_rate'));
    }

    /**
     * Gespeicherter Zeilenbetrag in seiner Spaltenpräzision (Rechnung: Cent,
     * LV: Bieterangabe mit 4 NK); ohne Spalte oder Wert gerechnet und auf die
     * Währungspräzision gerundet — dieselbe Regel, mit der die Rechnung ihre
     * Positionen speichert.
     */
    public function netAmount(): Money {
        return DocumentTotalsCalculator::moneyOrNull($this->lineValue('net_amount'), $this->lineCurrency())
            ?? $this->calculatedNetAmount();
    }

    /** Zeilennetto aus Menge, Einzelpreis und Positionsrabatt — ohne Blick auf die gespeicherte Spalte. */
    public function calculatedNetAmount(): Money {
        $currency = $this->lineCurrency();

        return DocumentTotalsCalculator::lineNet(
            $this->lineQuantity(),
            $this->unitPrice(),
            $this->discountPercent(),
            $this->discountAmount(),
            $currency,
        )->withScale($currency->getDefaultFractionDigits());
    }

    public function taxAmount(): Money {
        $net = $this->netAmount();
        $rate = $this->taxRate();

        return $rate === null ? Money::zero($net->getCurrency(), $net->getScale()) : $rate->amountOf($net);
    }

    public function grossAmount(): Money {
        return $this->netAmount()->plus($this->taxAmount());
    }

    private function lineValue(string $field): mixed {
        $column = static::lineColumn($field);

        return $column === null ? null : $this->getAttribute($column);
    }

    /** Belegkopf aus der geladenen Relation; lädt nach, wenn nötig. */
    private function lineDocumentModel(): ?Model {
        $document = $this->getRelationValue($this->lineDocument()->getRelationName());

        return $document instanceof Model ? $document : null;
    }
}
