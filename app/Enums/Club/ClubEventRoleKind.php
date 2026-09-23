<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEventRoleKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Terminrolle (Schiedsrichter, Zeitnehmer, Fahrdienst …): zählt als Teilnahme, nicht als Kaderplatz. */
enum ClubEventRoleKind: string implements HasLabel {
    use HasOptions;

    case Referee = 'referee';
    case Timekeeper = 'timekeeper';
    case Jury = 'jury';
    case Driver = 'driver';
    case VenueDuty = 'venue_duty';
    case RangeOfficer = 'range_officer';
    case Coach = 'coach';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.club.event-role-kind.' . $this->value);
    }

    public function icon(): string {
        return match ($this) {
            self::Referee => 'sports',
            self::Timekeeper => 'timer',
            self::Jury => 'gavel',
            self::Driver => 'directions_car',
            self::VenueDuty => 'cleaning_services',
            self::RangeOfficer => 'security',
            self::Coach => 'sports_handball',
            self::Other => 'person',
        };
    }
}
