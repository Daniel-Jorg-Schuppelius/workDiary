<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PresenceStatusKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Attendance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Selbstauskunft am Anwesenheits-Board (Feature 103, MVP-1669): der Stand,
 * den eine Person selbst setzt. „Kurz weg“ und „außer Haus“ brauchen ein
 * Ende; „nicht stören“ gilt ohne Ende bis zum Zurücksetzen oder Ausstempeln.
 * Kein Grund, kein Ort, kein Verlauf.
 */
enum PresenceStatusKind: string implements HasLabel {
    use HasOptions;

    case DoNotDisturb = 'do_not_disturb';
    case BriefAway = 'brief_away';
    case OutOfOffice = 'out_of_office';

    public function label(): string {
        return (string) __('presence.kind.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::DoNotDisturb => 'error',
            self::BriefAway => 'warning',
            self::OutOfOffice => 'info',
        };
    }

    public function icon(): string {
        return match ($this) {
            self::DoNotDisturb => 'do_not_disturb_on',
            self::BriefAway => 'directions_walk',
            self::OutOfOffice => 'location_away',
        };
    }

    /** Ende ist Pflicht („kurz weg“, „außer Haus“). */
    public function requiresUntil(): bool {
        return $this !== self::DoNotDisturb;
    }
}
