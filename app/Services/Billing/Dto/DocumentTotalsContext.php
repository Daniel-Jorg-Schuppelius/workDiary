<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentTotalsContext.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Dto;

use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\{Money, Percentage};

/**
 * Kopfdaten eines Belegs für die Summenrechnung (MVP-865): Währung,
 * Steuersatz-Rückfall für Positionen ohne eigenen Satz, Reverse Charge und
 * Belegrabatt (Prozent XOR Betrag).
 */
final readonly class DocumentTotalsContext {
    public function __construct(
        public CurrencyCode $currency = CurrencyCode::Euro,
        public ?Percentage $fallbackTaxRate = null,
        public bool $reverseCharge = false,
        public ?Percentage $discountPercent = null,
        public ?Money $discountAmount = null,
    ) {}
}
