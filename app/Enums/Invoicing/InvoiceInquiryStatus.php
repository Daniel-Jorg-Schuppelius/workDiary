<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceInquiryStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Bearbeitungsstand einer Rechnungsnachfrage (Feature 166, MVP-1199). */
enum InvoiceInquiryStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case Answered = 'answered';

    public function label(): string {
        return match ($this) {
            self::Open => (string) __('enums.invoicing.invoice_inquiry_status.open'),
            self::Answered => (string) __('enums.invoicing.invoice_inquiry_status.answered'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'warning',
            self::Answered => 'success',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Answered],
            self::Answered => [self::Open],
        };
    }
}
