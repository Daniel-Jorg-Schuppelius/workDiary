<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceRecognition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Woher die Werte eines Rechnungseingangs stammen (Feature 163, MVP-1107). */
enum IncomingInvoiceRecognition: string implements HasLabel {
    use HasOptions;

    /** XRechnung oder ZUGFeRD: die Werte gelten. */
    case Structured = 'structured';
    /** Aus PDF oder Bild erkannt: Vorschlag, am Original zu prüfen. */
    case Extracted = 'extracted';
    /** Nichts erkannt: Klärfall, die Werte erfasst der Mensch. */
    case None = 'none';

    public function label(): string {
        return match ($this) {
            self::Structured => (string) __('enums.invoicing.incoming_invoice_recognition.structured'),
            self::Extracted => (string) __('enums.invoicing.incoming_invoice_recognition.extracted'),
            self::None => (string) __('enums.invoicing.incoming_invoice_recognition.none'),
        };
    }
}
