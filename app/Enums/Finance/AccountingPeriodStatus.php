<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingPeriodStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zustand einer Buchungsperiode bzw. eines Geschäftsjahres (Feature 125,
 * MVP-671). Derselbe Enum für beide Ebenen: ein Jahr ist nicht mehr als die
 * Klammer um seine Perioden, und zwei fast gleiche Statuslisten wären eine
 * Fehlerquelle ohne Gegenwert.
 *
 * `soft_closed` ist der fachlich wichtige Zwischenschritt: Die Periode ist
 * inhaltlich fertig, aber eine berechtigte Korrektur ist noch möglich, ohne
 * den Wiedereröffnungs-Nachweis auszulösen.
 */
enum AccountingPeriodStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case SoftClosed = 'soft_closed';
    case Closed = 'closed';

    public function label(): string {
        return (string) __('enums.finance.accounting-period-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'success',
            self::SoftClosed => 'warning',
            self::Closed => 'neutral',
        };
    }

    /** Nimmt die Periode neue Festbuchungen an? */
    public function acceptsPostings(): bool {
        return $this === self::Open;
    }

    /** Endgültig geschlossen — Änderung nur nach nachgewiesener Wiedereröffnung. */
    public function isHardClosed(): bool {
        return $this === self::Closed;
    }
}
