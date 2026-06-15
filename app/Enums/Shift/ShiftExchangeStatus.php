<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftExchangeStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Shift;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Statusmaschine des Schichttauschs (Feature 007).
 *
 * requested → accepted → approved (Umsetzung)
 * requested/accepted → rejected (Ablehnung durch Leitung)
 * requested/accepted → cancelled (Rücknahme durch Antragsteller)
 */
enum ShiftExchangeStatus: string implements HasLabel {
    use HasOptions;

    case Requested = 'requested';
    case Accepted = 'accepted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('enums.shift.exchange_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Requested => 'warning',
            self::Accepted => 'info',
            self::Approved => 'success',
            self::Rejected => 'error',
            self::Cancelled => 'ghost',
        };
    }

    /** Endzustände — keine weiteren Übergänge möglich. */
    public function isFinal(): bool {
        return in_array($this, [self::Approved, self::Rejected, self::Cancelled], true);
    }

    /** Offene Anträge (noch nicht entschieden). */
    public function isOpen(): bool {
        return in_array($this, [self::Requested, self::Accepted], true);
    }

    /** Bereit zur Freigabe-Entscheidung durch die Leitung. */
    public function isDecidable(): bool {
        return $this->isOpen();
    }
}
