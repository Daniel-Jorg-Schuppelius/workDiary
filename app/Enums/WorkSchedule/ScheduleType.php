<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduleType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\WorkSchedule;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Arbeitszeit-Typ eines {@see \App\Models\WorkSchedule}. Steuert, wie das
 * Tagessoll ermittelt wird. Werte sind stabil (DB), Labels über
 * `work_schedule.type.<value>` übersetzt.
 *
 *  - Flextime:   Gleitzeit — einheitliches Tagessoll an Arbeitstagen, Kern-/Rahmenzeit.
 *  - Weekly:     Feste Wochenarbeitszeit — Tagessoll = Wochensoll ÷ Arbeitstage, keine Kernzeit.
 *  - PerWeekday: Wochentagsweise — pro Wochentag Stunden ODER Von–bis-Zeiten (day_targets).
 *  - Trust:      Vertrauensarbeitszeit — kein Soll (0), nur Ist/Anwesenheit.
 */
enum ScheduleType: string implements HasLabel {
    use HasOptions;

    case Flextime = 'flextime';
    case Weekly = 'weekly';
    case PerWeekday = 'per_weekday';
    case Trust = 'trust';

    public function label(): string {
        return (string) __('work_schedule.type.' . $this->value);
    }

    /** Ob dieser Typ überhaupt ein Soll führt (false = reine Vertrauensarbeitszeit). */
    public function tracksTarget(): bool {
        return $this !== self::Trust;
    }

    /** Ob Kern-/Rahmenzeit-Regeln greifen (nur Gleitzeit). */
    public function usesCoreTime(): bool {
        return $this === self::Flextime;
    }

    /** Badge-Farbton (daisyUI tone) für die Modell-Anzeige. */
    public function badgeTone(): string {
        return match ($this) {
            self::Flextime => 'primary',
            self::Weekly => 'info',
            self::PerWeekday => 'info',
            self::Trust => 'warning',
        };
    }

    /** Material-Symbols-Icon für die Modell-Anzeige. */
    public function icon(): string {
        return match ($this) {
            self::Flextime => 'schedule',
            self::Weekly => 'date_range',
            self::PerWeekday => 'calendar_view_week',
            self::Trust => 'handshake',
        };
    }
}
