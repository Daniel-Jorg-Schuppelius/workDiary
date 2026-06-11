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
 * Art einer Zuschlagsregel (Feature 005, MVP).
 *
 * - night:    Zeitfenster (window_start/window_end), darf über Mitternacht gehen
 * - saturday: ganzer Samstag
 * - sunday:   ganzer Sonntag
 * - holiday:  ganzer gesetzlicher/organisationsweiter Feiertag (HolidayService)
 * - custom:   freies Zeitfenster (wie night, semantisch eigenständig)
 */
enum SurchargeKind: string implements HasLabel {
    use HasOptions;

    case Night = 'night';
    case Saturday = 'saturday';
    case Sunday = 'sunday';
    case Holiday = 'holiday';
    case Custom = 'custom';

    public function label(): string {
        return __('enums.surcharge.kind.' . $this->value);
    }

    /** Benötigt diese Art ein Zeitfenster (window_start/window_end)? */
    public function requiresWindow(): bool {
        return $this === self::Night || $this === self::Custom;
    }

    public function tone(): string {
        return match ($this) {
            self::Night => 'info',
            self::Saturday => 'primary',
            self::Sunday => 'warning',
            self::Holiday => 'error',
            self::Custom => 'ghost',
        };
    }

    public function icon(): string {
        return match ($this) {
            self::Night => 'bedtime',
            self::Saturday, self::Sunday => 'calendar_view_week',
            self::Holiday => 'celebration',
            self::Custom => 'tune',
        };
    }
}
