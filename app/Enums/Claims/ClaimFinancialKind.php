<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimFinancialKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Claims;

/**
 * Kaufmännische Folge (MVP-252, Entscheidung D1): KEIN neuer Belegtyp —
 * die Art lebt hier; auf Faktura-Seite bleibt es bei Gutschrift/Storno,
 * ergänzt um das strukturierte reason_kind-Feld am Beleg.
 */
enum ClaimFinancialKind: string {
    case PriceReduction = 'price_reduction';
    case CreditNote = 'credit_note';
    case Cancellation = 'cancellation';
    case Correction = 'correction';
    case ReplacementInvoice = 'replacement_invoice';
    case Refund = 'refund';

    public function label(): string {
        return match ($this) {
            self::PriceReduction => (string) __('Minderung/Preisnachlass'),
            self::CreditNote => (string) __('Gutschrift'),
            self::Cancellation => (string) __('Storno'),
            self::Correction => (string) __('Rechnungskorrektur'),
            self::ReplacementInvoice => (string) __('Ersatzrechnung'),
            self::Refund => (string) __('Rückerstattung'),
        };
    }

    /** Erzeugt die Ausführung einen Faktura-Folgebeleg? */
    public function producesInvoice(): bool {
        return in_array($this, [self::PriceReduction, self::CreditNote, self::Cancellation, self::Correction], true);
    }
}
