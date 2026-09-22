<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubParticipationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Stand einer Mitgliedsteilnahme am Vereinstermin (MVP-843): eingeladen,
 * angemeldet, Warteliste, abgesagt. Anwesenheit ist ein eigener Nachweis
 * (MVP-844) und steht nie hier.
 */
enum ClubParticipationStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;

    case Invited = 'invited';
    case Registered = 'registered';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('enums.club.participation-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Invited => 'info',
            self::Registered => 'success',
            self::Waitlisted => 'warning',
            self::Cancelled => 'ghost',
        };
    }

    /** Belegt oder wartet — zählt als laufende Teilnahme. */
    public function isActive(): bool {
        return $this === self::Registered || $this === self::Waitlisted;
    }

    /** @return list<ClubParticipationStatus> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Invited => [self::Registered, self::Waitlisted, self::Cancelled],
            self::Registered => [self::Cancelled],
            self::Waitlisted => [self::Registered, self::Cancelled],
            self::Cancelled => [self::Registered, self::Waitlisted],
        };
    }
}
