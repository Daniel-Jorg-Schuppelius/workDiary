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

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Kaufmännische Folge (MVP-252, Entscheidung D1): KEIN neuer Belegtyp —
 * die Art lebt hier; auf Faktura-Seite bleibt es bei Gutschrift/Storno,
 * ergänzt um das strukturierte reason_kind-Feld am Beleg.
 */
enum ClaimFinancialKind: string implements HasLabel {
    use HasOptions;

    case PriceReduction = 'price_reduction';
    case CreditNote = 'credit_note';
    case Cancellation = 'cancellation';
    case Correction = 'correction';
    case ReplacementInvoice = 'replacement_invoice';
    case Refund = 'refund';

    public function label(): string {
        return match ($this) {
            self::PriceReduction => (string) __('enums.claims.claim_financial_kind.price_reduction'),
            self::CreditNote => (string) __('enums.claims.claim_financial_kind.credit_note'),
            self::Cancellation => (string) __('enums.claims.claim_financial_kind.cancellation'),
            self::Correction => (string) __('enums.claims.claim_financial_kind.correction'),
            self::ReplacementInvoice => (string) __('enums.claims.claim_financial_kind.replacement_invoice'),
            self::Refund => (string) __('enums.claims.claim_financial_kind.refund'),
        };
    }

    /** Erzeugt die Ausführung einen Faktura-Folgebeleg? */
    public function producesInvoice(): bool {
        return in_array($this, [self::PriceReduction, self::CreditNote, self::Cancellation, self::Correction], true);
    }
}
