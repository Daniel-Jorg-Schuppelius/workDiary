<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayClosureStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\TimeApproval;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status eines Tagesabschlusses (MVP-015, ../WorkDiary-Architecture/tagesabschluss.md §3).
 *
 * Persistiert werden nur open|closed|correction. `locked` ist ein
 * abgeleiteter Anzeige-Status: ein Tag gilt als gesperrt, sobald sein
 * Monat in der Monatsfreigabe (MVP-016) submitted|approved|locked ist —
 * siehe {@see \App\Services\TimeApproval\DayCloseService::isMonthLocked()}.
 * String-backed statt tinyint gemäß Haus-Konvention (vgl. MonthClosureStatus).
 */
enum DayClosureStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Closed = 'closed';
    case Correction = 'correction';
    case Locked = 'locked';

    public function label(): string {
        return (string) __('enums.dayClosure.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open       => 'ghost',
            self::Closed     => 'success',
            self::Correction => 'warning',
            self::Locked     => 'secondary',
        };
    }
}
