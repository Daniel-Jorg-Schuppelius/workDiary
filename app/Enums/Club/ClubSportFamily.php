<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubSportFamily.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Sportfamilie eines Sportartenprofils (Feature 159, MVP-852) — Konfiguration, kein Code-Sonderfall je Sportart. */
enum ClubSportFamily: string implements HasLabel {
    use HasOptions;

    case TeamBall = 'team_ball';
    case Racket = 'racket';
    case Individual = 'individual';
    case Shooting = 'shooting';
    case Equestrian = 'equestrian';
    case MartialArts = 'martial_arts';
    case Boats = 'boats';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.club.sport-family.' . $this->value);
    }

    public function icon(): string {
        return match ($this) {
            self::TeamBall => 'sports_soccer',
            self::Racket => 'sports_tennis',
            self::Individual => 'directions_run',
            self::Shooting => 'gps_fixed',
            self::Equestrian => 'bedroom_baby',
            self::MartialArts => 'sports_martial_arts',
            self::Boats => 'rowing',
            self::Other => 'sports',
        };
    }

    /** Mannschafts- und Rückschlagsport tragen Spieltage, Kader und Aufstellungen. */
    public function hasMatches(): bool {
        return $this === self::TeamBall || $this === self::Racket;
    }
}
