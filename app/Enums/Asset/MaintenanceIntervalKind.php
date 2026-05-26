<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenanceIntervalKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Asset;

enum MaintenanceIntervalKind: string {
    case Days = 'days';
    case Weeks = 'weeks';
    case Months = 'months';
    case OperatingHours = 'operating_hours';
    case Kilometers = 'km';

    /** True when interval can be progressed purely on the calendar (no usage meter required). */
    public function isCalendarBased(): bool {
        return match ($this) {
            self::Days, self::Weeks, self::Months => true,
            self::OperatingHours, self::Kilometers => false,
        };
    }
}
