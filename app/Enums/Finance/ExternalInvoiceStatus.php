<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand einer extern ausgestellten Rechnung (Feature 196): gespiegelt aus dem
 * führenden Fakturasystem, nie in WorkDiary gesetzt. Ausstellung, Versand und
 * Zahlung bleiben getrennte Zustände.
 */
enum ExternalInvoiceStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Open = 'open';
    case Paid = 'paid';
    case Voided = 'voided';
    case Unknown = 'unknown';

    public function label(): string {
        return (string) __('enums.finance.external-invoice-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Open => 'info',
            self::Paid => 'success',
            self::Voided => 'error',
            self::Unknown => 'warning',
        };
    }

    /** Extern festgeschrieben: Nummer und Inhalt stehen, das Original darf abgerufen werden. */
    public function isFinalized(): bool {
        return $this === self::Open || $this === self::Paid;
    }
}
