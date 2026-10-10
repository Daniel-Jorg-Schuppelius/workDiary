<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceMatchKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Weg, auf dem ein Rechnungseingang seiner Gegenpartei zugeordnet wurde (Feature 163, MVP-1108). */
enum IncomingInvoiceMatchKind: string implements HasLabel {
    use HasOptions;

    case VatId = 'vat_id';
    case TaxNumber = 'tax_number';
    case Iban = 'iban';
    case SenderRule = 'sender_rule';
    case Manual = 'manual';

    public function label(): string {
        return match ($this) {
            self::VatId => (string) __('enums.invoicing.incoming_invoice_match_kind.vat_id'),
            self::TaxNumber => (string) __('enums.invoicing.incoming_invoice_match_kind.tax_number'),
            self::Iban => (string) __('enums.invoicing.incoming_invoice_match_kind.iban'),
            self::SenderRule => (string) __('enums.invoicing.incoming_invoice_match_kind.sender_rule'),
            self::Manual => (string) __('enums.invoicing.incoming_invoice_match_kind.manual'),
        };
    }
}
