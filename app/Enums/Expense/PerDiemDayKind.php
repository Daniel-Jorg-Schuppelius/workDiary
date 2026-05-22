<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemDayKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Expense;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art eines Reisetags für die Pauschalen-Berechnung:
 *
 *  - DepartureDay: Anreisetag einer Mehrtages-Reise (partial_day_amount)
 *  - FullDay: voller Reisetag (full_day_amount)
 *  - ReturnDay: Abreisetag einer Mehrtages-Reise (partial_day_amount)
 *  - SingleDay: Eintagesreise > 8 h (partial_day_amount)
 */
enum PerDiemDayKind: string implements HasLabel {
    use HasOptions;

    case DepartureDay = 'departure_day';
    case FullDay = 'full_day';
    case ReturnDay = 'return_day';
    case SingleDay = 'single_day';

    public function label(): string {
        return (string) __('enums.per_diem.day_kind.' . $this->value);
    }

    /** Tagessatz vor Kürzung: Volltag oder Anteilssatz. */
    public function usesFullDayAmount(): bool {
        return $this === self::FullDay;
    }
}
