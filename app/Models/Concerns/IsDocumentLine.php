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

use App\Enums\Billing\DocumentLineKind;
use App\Models\Contracts\{DocumentLine, HasDocumentLines};
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
     * discount_amount, tax_rate, net_amount, currency, labour_share_percent,
     * line_kind.
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

    /** Vorbelegung des Arbeitsanteils beim Anlegen (MVP-1053), nur wo das Modell die Spalte führt. */
    public static function bootIsDocumentLine(): void {
        static::creating(static function (DocumentLine&Model $line): void {
            $column = static::lineColumn('labour_share_percent');
            if ($column === null || $line->getAttribute($column) !== null || ! method_exists($line, 'defaultLabourShare')
                || ! $line->lineKind()->isPriced()) {
                return;
            }
            $share = $line->defaultLabourShare();
            if ($share !== null) {
                $line->setAttribute($column, (string) $share);
            }
        });
    }

    /**
     * Arbeitsanteil in Prozent, den eine neue Position ohne eigene Angabe erhält; Modelle mit Quellbezug überschreiben.
     *
     * @return int|numeric-string|null
     */
    public function defaultLabourShare(): int|string|null {
        return $this->articleLabourShare();
    }

    /**
     * Kalkulierte Leistung (MVP-1055): Anteil aus der Kalkulation, sonst nach Artikelart.
     *
     * @return int|numeric-string|null
     */
    protected function articleLabourShare(): int|string|null {
        if ($this->getAttribute('article_id') === null || ! method_exists($this, 'article')) {
            return null;
        }
        $article = $this->article()->first();
        if (! $article instanceof \App\Models\Article\Article) {
            return null;
        }
        $calculated = app(\App\Services\Article\ServiceCalculationService::class)->calculate($article)?->labourShare;
        if ($calculated !== null) {
            return $calculated->getNumericValue();
        }

        return $article->type->defaultLabourShare();
    }

    public function lineKind(): DocumentLineKind {
        $value = $this->lineValue('line_kind');

        return $value instanceof DocumentLineKind ? $value : (DocumentLineKind::tryFrom((string) $value) ?? DocumentLineKind::Item);
    }

    public function labourShare(): ?Percentage {
        return DocumentTotalsCalculator::percent($this->lineValue('labour_share_percent'));
    }

    /** @return array<string, mixed> */
    public function carriedLineAttributes(): array {
        $carried = [];
        foreach (['labour_share_percent', 'line_kind', 'unit_cost_amount'] as $field) {
            $column = static::lineColumn($field);
            if ($column !== null && array_key_exists($column, $this->getAttributes())) {
                $carried[$field] = $this->getAttribute($column);
            }
        }

        return $carried;
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
