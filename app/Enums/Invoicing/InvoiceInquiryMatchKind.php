<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceInquiryMatchKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Wie eine Mail zur Rechnung gefunden wurde (Feature 166, MVP-1199). */
enum InvoiceInquiryMatchKind: string implements HasLabel {
    use HasOptions;

    /** Antwort auf eine von WorkDiary versandte Rechnung (In-Reply-To/References). */
    case Thread = 'thread';

    /** Rechnungs- oder Belegnummer in Betreff oder Text. */
    case Number = 'number';

    /** Von Hand aus der Zuordnungs-Inbox. */
    case Manual = 'manual';

    public function label(): string {
        return match ($this) {
            self::Thread => (string) __('enums.invoicing.invoice_inquiry_match_kind.thread'),
            self::Number => (string) __('enums.invoicing.invoice_inquiry_match_kind.number'),
            self::Manual => (string) __('enums.invoicing.invoice_inquiry_match_kind.manual'),
        };
    }
}
