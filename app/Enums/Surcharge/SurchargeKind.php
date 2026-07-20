<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurchargeKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Surcharge;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art einer Zuschlagsregel (Feature 005, MVP + Vollaudit 2026-07, M4).
 *
 * - night:    Zeitfenster (window_start/window_end), darf über Mitternacht gehen
 * - saturday: ganzer Samstag
 * - sunday:   ganzer Sonntag
 * - holiday:  ganzer gesetzlicher/organisationsweiter Feiertag (HolidayService)
 * - custom:   freies Zeitfenster (wie night, semantisch eigenständig)
 * - oncall:   Bereitschaftsdienst — Quelle sind OnCallShift-Stunden im Zeitraum
 * - standby:  Rufbereitschaft — Quelle sind TimeEntries mit activity_type=standby
 * - overtime: Überstunden — Quelle ist die Monatssoll-Überschreitung (FlexCalculator)
 *
 * Entscheidung M4: oncall/standby/overtime sind KEINE Intervall-Regeln — sie
 * zerlegen keine Arbeitszeit-Intervalle und nehmen deshalb nicht am
 * max-Stacking mit Nacht/Wochenende/Feiertag teil (eigene Quellzeiten, die
 * außerhalb bzw. quer zur Attendance liegen). Aggregation im Zeit-Export.
 */
enum SurchargeKind: string implements HasLabel {
    use HasOptions;

    case Night = 'night';
    case Saturday = 'saturday';
    case Sunday = 'sunday';
    case Holiday = 'holiday';
    case Custom = 'custom';
    case OnCall = 'oncall';
    case Standby = 'standby';
    case Overtime = 'overtime';

    public function label(): string {
        return __('enums.surcharge.kind.' . $this->value);
    }

    /** Benötigt diese Art ein Zeitfenster (window_start/window_end)? */
    public function requiresWindow(): bool {
        return $this === self::Night || $this === self::Custom;
    }

    /**
     * Zerlegt diese Art Arbeitszeit-Intervalle (SurchargeCalculator, max-
     * Stacking)? oncall/standby/overtime haben eigene Quellzeiten und werden
     * im Zeit-Export separat aggregiert (M4-Entscheidung, kein Stacking).
     */
    public function isIntervalBased(): bool {
        return match ($this) {
            self::Night, self::Saturday, self::Sunday, self::Holiday, self::Custom => true,
            self::OnCall, self::Standby, self::Overtime => false,
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Night => 'info',
            self::Saturday => 'primary',
            self::Sunday => 'warning',
            self::Holiday => 'error',
            self::Custom => 'ghost',
            self::OnCall => 'accent',
            self::Standby => 'secondary',
            self::Overtime => 'neutral',
        };
    }

    public function icon(): string {
        return match ($this) {
            self::Night => 'bedtime',
            self::Saturday, self::Sunday => 'calendar_view_week',
            self::Holiday => 'celebration',
            self::Custom => 'tune',
            self::OnCall => 'e911_emergency',
            self::Standby => 'phone_in_talk',
            self::Overtime => 'more_time',
        };
    }
}
