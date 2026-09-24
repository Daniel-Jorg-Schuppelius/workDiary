<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contracts;

use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\{Money, Percentage};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Belegposition (MVP-865): Menge × Einzelpreis abzüglich Positionsrabatt
 * (Prozent XOR Betrag), Steuersatz je Zeile. Physische Spalten dürfen
 * abweichen (`vat_rate`, `unit_name`, `total_price`); der Trait
 * {@see \App\Models\Concerns\IsDocumentLine} bildet sie ab. Beträge rechnet
 * ausschließlich {@see \App\Services\Billing\DocumentTotalsCalculator}.
 */
interface DocumentLine {
    /**
     * Belegkopf der Position (Rechnung, Angebot, LV, …).
     *
     * @return BelongsTo<covariant Model, covariant Model>
     */
    public function lineDocument(): BelongsTo;

    public function linePosition(): int;

    /** @return numeric-string */
    public function lineQuantity(): string;

    public function lineUnit(): ?string;

    /** Belegwährung: eigene Spalte, sonst die des Kopfes, sonst Euro. */
    public function lineCurrency(): CurrencyCode;

    public function unitPrice(): ?Money;

    public function discountPercent(): ?Percentage;

    public function discountAmount(): ?Money;

    public function taxRate(): ?Percentage;

    /** Zeilennetto: gespeicherter Betrag, sonst gerechnet. */
    public function netAmount(): Money;

    /** Steuer dieser Zeile — informativ; Belegsteuer entsteht je Satzgruppe. */
    public function taxAmount(): Money;

    public function grossAmount(): Money;
}
